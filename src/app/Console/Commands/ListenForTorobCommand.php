<?php

namespace App\Console\Commands;

use App\Services\ScrapeOpsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Sleep;

/**
 * Listens for Torob scrape requests and fetches each product's sellers
 * through ScrapeOps inline (no queued job, no Horizon supervisor).
 *
 * The loop is a single process, so the fixed pause between requests is
 * the only throttle needed: one request every `scrapeops.sleep_seconds`.
 */
class ListenForTorobCommand extends Command
{
    protected $signature = 'listen:torob {--once : Process a single message and exit}';

    protected $description = 'listens for torob scrape requests and fetches each through ScrapeOps';
    public function handle(): void
    {
        $this->info('listen:torob listener command started');

        while (true) {
            try {
                $message = Redis::blpop(config('queue.TOROB_QUEUE_IN'), 0);

                if (empty($message)) {
                    continue;
                }

                $data = json_decode($message[1], true);

                $this->info('Message received for product_id: '.data_get($data, 'product.id'));

                $this->processMessage($data);

                if ($this->option('once')) {
                    break;
                }
            } catch (\Throwable $e) {
                Log::error('error in listen:torob command', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error('error happened: '.json_encode($e->getMessage()));

                if ($this->option('once')) {
                    break;
                }
            }
        }
    }

    private function processMessage(array $data): void
    {
        $productId = data_get($data, 'product.id');
        $fullUrl = data_get($data, 'product.full_url');
        $bulk = data_get($data, 'bulk', false);

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
            $response = app(ScrapeOpsService::class)->get($fullUrl);

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

            Cache::put($cacheKey, $payload, now()->addDays((int) config('services.scrapeops.cache_ttl_days', 1)));

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
