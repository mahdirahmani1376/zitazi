<?php

namespace App\Jobs;

use App\Services\ScrapeOpsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Sleep;

/**
 * Fetches a Torob product's sellers via ScrapeOps and pushes the result back
 * onto the `scrape_result` queue that `listen:scrape` consumes, with the same
 * response shape the old Puppeteer TorobStrategy produced.
 *
 * Each run counts against the ScrapeOps free-plan request quota, so:
 *  - the job never retries automatically (a retry would burn another request),
 *  - successful responses are cached for a month per product,
 *  - a global request counter stops at the configured cap (default 800),
 *  - a fixed pause before each HTTP request keeps the free-tier proxy calm
 *    (default 7 seconds, configurable via `scrapeops.sleep_seconds`).
 */
class FetchTorobWithScrapeOpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    public function __construct(
        public array $data,
    ) {
        // Dedicated single-process queue so concurrent Horizon workers cannot
        // burst past the ScrapeOps request budget (see horizon.php
        // `supervisor-torob-scrape`).
        $this->onQueue('torob-scrape');
    }

    public function handle(ScrapeOpsService $scrapeOpsService): void
    {
        $productId = data_get($this->data, 'product.id');
        $fullUrl = data_get($this->data, 'product.full_url');
        $bulk = data_get($this->data, 'bulk', false);

        if (empty($productId) || empty($fullUrl)) {
            $this->pushResult($this->failure($productId, $fullUrl, 'empty url provided'));

            return;
        }

        $cacheKey = "torob:scrapeops:{$productId}";

        if ($cached = Cache::get($cacheKey)) {
            $this->pushResult($this->success($productId, $cached, $fullUrl, $bulk));

            return;
        }

        if ($this->quotaExceeded()) {
            $this->pushResult($this->failure($productId, $fullUrl, 'ScrapeOps quota exceeded', [
                'name' => 'ScrapeOpsQuotaExceeded',
                'message' => 'ScrapeOps request quota exceeded',
            ]));

            return;
        }

        // Space out requests with a fixed pause so the free-tier proxy is not
        // hammered. A plain sleep (no bucket, no log noise).
        $this->sleepBetweenRequests();

        $this->incrementUsage();

        try {
            $response = $scrapeOpsService->get($fullUrl);

            if ($response['status'] === 404) {
                $this->pushResult($this->failure($productId, $fullUrl, 'product not found', [
                    'name' => 'NotFoundHttpException',
                    'message' => 'Product not found on Torob',
                ], deleted: true));

                return;
            }

            $json = $response['json'];

            if (empty($json) || empty(data_get($json, 'products_info'))) {
                $this->pushResult($this->failure($productId, $fullUrl, 'non-json or empty response', [
                    'name' => 'ScrapeOpsInvalidResponse',
                    'message' => 'ScrapeOps returned no products_info for '.$fullUrl,
                ], responseStatus: $response['status']));

                return;
            }

            $payload = [
                'result' => data_get($json, 'products_info.result'),
                'product' => data_get($json, 'products_info'),
            ];

            Cache::put($cacheKey, $payload, now()->addDays((int) config('services.scrapeops.cache_ttl_days', 30)));

            $this->pushResult($this->success($productId, $payload, $fullUrl, $bulk, $response['status']));
        } catch (\Throwable $e) {
            Log::error('error fetching torob via scrapeops', [
                'product_id' => $productId,
                'url' => $fullUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->pushResult($this->failure($productId, $fullUrl, $e->getMessage(), [
                'name' => $e::class,
                'message' => $e->getMessage(),
            ]));
        }
    }

    private function quotaExceeded(): bool
    {
        $limit = (int) config('services.scrapeops.max_requests', 800);

        return (int) Cache::get($this->usageKey(), 0) >= $limit;
    }

    /**
     * Pause before sending an HTTP request so requests are spaced out.
     * Duration comes from `scrapeops.sleep_seconds` (default 7 seconds).
     */
    private function sleepBetweenRequests(): void
    {
        Sleep::for((int) config('services.scrapeops.sleep_seconds', 7))->seconds();
    }

    private function incrementUsage(): void
    {
        Cache::increment($this->usageKey());
    }

    private function usageKey(): string
    {
        return 'scrapeops:requests';
    }

    private function pushResult(array $result): void
    {
        Redis::rpush('scrape_result', json_encode($result));
    }

    private function success(int $productId, array $payload, string $fullUrl, bool $bulk, ?int $status = 200): array
    {
        return [
            'product_id' => $productId,
            'response_data' => $payload,
            'response_status' => $status,
            'headers' => [],
            'url' => $fullUrl,
            'full_url' => $fullUrl,
            'success' => true,
            'source' => 'Torob',
            'bulk' => $bulk,
        ];
    }

    private function failure(
        ?int $productId,
        ?string $fullUrl,
        string $message,
        array $error = [],
        bool $deleted = false,
        ?int $responseStatus = null,
    ): array {
        return [
            'product_id' => $productId,
            'full_url' => $fullUrl,
            'success' => false,
            'blocked' => false,
            'deleted' => $deleted,
            'retry_count' => 0,
            'error' => $error ?: ['name' => 'Error', 'message' => $message],
            'response_status' => $responseStatus,
            'source' => 'Torob',
        ];
    }
}
