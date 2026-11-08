<?php

namespace App\Console\Commands;

use App\Actions\LogManager;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Product;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ResyncDeletedVariationsCommand extends Command
{
    /**
     * Status codes worth retrying: rate limiting and server-side failures.
     */
    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    protected $signature = 'app:resync-deleted-variations
        {--source= : Only resync variations for this destination store (zitazi|satre)}
        {--product-id=* : Only resync variations belonging to these product ids}
        {--variation-id=* : Only resync these variation ids}
        {--limit= : Stop after resyncing this many variations}
        {--chunk=200 : Number of variations fetched per database chunk}
        {--retries=2 : Extra attempts per variation when the request fails}
        {--sleep=0 : Milliseconds to wait between variations}
        {--dry-run : Show what would be sent without calling the destination store}';

    protected $description = 'Re-send the out-of-stock update for soft-deleted variations so the destination store removes them';

    public function handle(): int
    {
        $source = $this->normalizeSource($this->option('source'));

        if ($source === false) {
            $this->error('Invalid --source ['.$this->option('source').']. Allowed values: '.implode(', ', [Product::ZITAZI, Product::SATRE]));

            return self::FAILURE;
        }

        $query = $this->buildQuery(
            source: $source,
            productIds: array_map('strval', (array) $this->option('product-id')),
            variationIds: array_map('intval', (array) $this->option('variation-id')),
        );

        $limit = $this->option('limit') === null ? null : max(1, (int) $this->option('limit'));
        $matching = (clone $query)->count();
        $total = $limit === null ? $matching : min($limit, $matching);

        if ($total === 0) {
            $this->components->info('No soft-deleted variations found. Nothing to resync.');

            return self::SUCCESS;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $attempts = max(1, 1 + (int) $this->option('retries'));
        $sleepMilliseconds = max(0, (int) $this->option('sleep'));
        $chunkSize = max(1, (int) $this->option('chunk'));

        $this->components->info(sprintf(
            '%s %d soft-deleted variation(s)%s.',
            $isDryRun ? 'Dry run: would resync' : 'Resyncing',
            $total,
            $source === null ? '' : " for [{$source}]"
        ));

        if ($isDryRun) {
            $this->components->warn('Dry run enabled: no HTTP requests will be sent.');
        }

        $stats = [
            'succeeded' => 0,
            'failed' => 0,
            'skipped' => 0,
            'dry_run' => 0,
        ];

        /** @var array<int, array{status: string, variation_id: int, product_id: string|null, reason: string}> $problems */
        $problems = [];

        $processed = 0;
        $startedAt = microtime(true);

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %elapsed:6s% elapsed');
        $bar->start();

        $query->chunkById($chunkSize, function (Collection $variations) use (
            &$stats,
            &$problems,
            &$processed,
            $bar,
            $isDryRun,
            $attempts,
            $sleepMilliseconds,
            $limit
        ) {
            foreach ($variations as $variation) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                $result = $this->resyncVariation($variation, $isDryRun, $attempts);

                $stats[$result['status']]++;
                $processed++;

                if (in_array($result['status'], ['failed', 'skipped'], true) && count($problems) < 200) {
                    $problems[] = $result;
                }

                $bar->advance();

                if ($sleepMilliseconds > 0 && ! $isDryRun) {
                    usleep($sleepMilliseconds * 1000);
                }
            }

            return true;
        });

        $bar->finish();
        $this->newLine(2);

        $this->renderSummary($stats, $processed, microtime(true) - $startedAt, $isDryRun);
        $this->renderProblems($problems);

        Log::info('resync deleted variations finished', $stats + [
            'processed' => $processed,
            'dry_run' => $isDryRun,
        ]);

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return string|null|false The normalized source, null when unfiltered, false when invalid.
     */
    private function normalizeSource(?string $source): string|null|false
    {
        if ($source === null || $source === '') {
            return null;
        }

        $source = strtolower(trim($source));

        return in_array($source, [Product::ZITAZI, Product::SATRE], true) ? $source : false;
    }

    /**
     * @param  array<int, string>  $productIds
     * @param  array<int, int>  $variationIds
     */
    private function buildQuery(?string $source, array $productIds, array $variationIds): Builder
    {
        return Variation::query()
            ->onlyTrashed()
            ->with([
                'product' => fn (BelongsTo $query) => $query
                    ->withTrashed()
                    ->withCount(['variations' => fn (Builder $query) => $query->withTrashed()]),
            ])
            ->when($source !== null, fn (Builder $query) => $query->where('base_source', $source))
            ->when($productIds !== [], fn (Builder $query) => $query->whereIn('product_id', $productIds))
            ->when($variationIds !== [], fn (Builder $query) => $query->whereIn('id', $variationIds));
    }

    /**
     * @return array{status: string, variation_id: int, product_id: string|null, reason: string}
     */
    private function resyncVariation(Variation $variation, bool $isDryRun, int $attempts): array
    {
        $product = $variation->product;

        if ($product === null) {
            return $this->skip($variation, 'variation has no related product');
        }

        if (empty($product->own_id)) {
            return $this->skip($variation, 'product is missing own_id');
        }

        if (! in_array($variation->base_source, [Product::ZITAZI, Product::SATRE], true)) {
            return $this->skip($variation, "unsupported base_source [{$variation->base_source}]");
        }

        $url = $this->buildUrl($variation);

        if ($url === null) {
            return $this->skip($variation, 'no route to resync (missing own_id and product has multiple variations)');
        }

        $body = ZitaziUpdateDTO::getFullPayloadFromPriceAndStock();

        if ($isDryRun) {
            Log::info('resync deleted variation dry run', [
                'variation_id' => $variation->id,
                'product_id' => $variation->product_id,
                'url' => $url,
                'body' => $body,
            ]);

            return $this->result('dry_run', $variation, 'would POST '.$url);
        }

        ['response' => $response, 'exception' => $exception, 'attempts' => $attemptsUsed] = $this->sendWithRetries($variation, $url, $body, $attempts);

        if ($response instanceof Response && $response->successful()) {
            LogManager::logVariation($variation, 'resync deleted variation success', [
                'url' => $url,
                'request_body' => $body,
                'status_code' => $response->status(),
                'attempts' => $attemptsUsed,
                'response' => Str::limit($response->body(), 1000),
            ]);

            return $this->result('succeeded', $variation, 'HTTP '.$response->status());
        }

        $reason = $exception !== null
            ? $exception->getMessage()
            : ($response instanceof Response ? 'HTTP '.$response->status() : 'no response received');

        LogManager::logVariation($variation, 'resync deleted variation failed', [
            'url' => $url,
            'request_body' => $body,
            'status_code' => $response?->status(),
            'attempts' => $attemptsUsed,
            'response' => $response === null ? null : Str::limit($response->body(), 1000),
            'exception' => $exception?->getMessage(),
        ]);

        Log::warning('resync deleted variation failed', [
            'variation_id' => $variation->id,
            'product_id' => $variation->product_id,
            'url' => $url,
            'reason' => $reason,
            'attempts' => $attemptsUsed,
        ]);

        return $this->result('failed', $variation, $reason);
    }

    /**
     * Mirror the routing used by the variation crawler: variations with an
     * own_id update their own resource, single-variation / product-update
     * entries update the parent product. Anything else has no safe route.
     */
    private function buildUrl(Variation $variation): ?string
    {
        $product = $variation->product;

        if (! empty($variation->own_id)) {
            return "products/{$product->own_id}/variations/{$variation->own_id}";
        }

        return "products/{$product->own_id}";
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{response: Response|null, exception: Throwable|null, attempts: int}
     */
    private function sendWithRetries(Variation $variation, string $url, array $body, int $attempts): array
    {
        $response = null;
        $exception = null;
        $attemptsUsed = 0;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $attemptsUsed = $attempt;

            try {
                $response = WoocommerceService::sendRequest($url, $body, 'post', $variation->base_source);
                $exception = null;

                if ($response->successful()) {
                    return ['response' => $response, 'exception' => null, 'attempts' => $attemptsUsed];
                }

                if (! in_array($response->status(), self::RETRYABLE_STATUSES, true)) {
                    break;
                }
            } catch (Throwable $throwable) {
                $exception = $throwable;
                $response = null;
            }

            if ($attempt < $attempts) {
                usleep(250_000 * $attempt);
            }
        }

        return ['response' => $response, 'exception' => $exception, 'attempts' => $attemptsUsed];
    }

    /**
     * @return array{status: string, variation_id: int, product_id: string|null, reason: string}
     */
    private function skip(Variation $variation, string $reason): array
    {
        Log::warning('resync deleted variation skipped', [
            'variation_id' => $variation->id,
            'product_id' => $variation->product_id,
            'reason' => $reason,
        ]);

        return $this->result('skipped', $variation, $reason);
    }

    /**
     * @return array{status: string, variation_id: int, product_id: string|null, reason: string}
     */
    private function result(string $status, Variation $variation, string $reason): array
    {
        return [
            'status' => $status,
            'variation_id' => $variation->id,
            'product_id' => $variation->product_id,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function renderSummary(array $stats, int $processed, float $elapsedSeconds, bool $isDryRun): void
    {
        $this->table(
            ['Result', 'Count'],
            [
                ['Succeeded', $stats['succeeded']],
                ['Failed', $stats['failed']],
                ['Skipped', $stats['skipped']],
                ...($isDryRun ? [['Would send (dry run)', $stats['dry_run']]] : []),
                ['Processed', $processed],
            ]
        );

        $summary = sprintf(
            'Finished in %.2fs — %d succeeded, %d failed, %d skipped%s.',
            $elapsedSeconds,
            $stats['succeeded'],
            $stats['failed'],
            $stats['skipped'],
            $isDryRun ? ", {$stats['dry_run']} would be sent" : ''
        );

        if ($stats['failed'] > 0) {
            $this->components->warn($summary);
        } else {
            $this->components->info($summary);
        }
    }

    /**
     * @param  array<int, array{status: string, variation_id: int, product_id: string|null, reason: string}>  $problems
     */
    private function renderProblems(array $problems): void
    {
        if ($problems === []) {
            return;
        }

        $visible = array_slice($problems, 0, 50);

        $this->components->warn(sprintf(
            '%d variation(s) were not resynced%s:',
            count($problems),
            count($problems) > count($visible) ? ' (showing first 50, see logs for the rest)' : ''
        ));

        $this->table(
            ['Variation', 'Product', 'Status', 'Reason'],
            array_map(fn (array $problem) => [
                $problem['variation_id'],
                $problem['product_id'] ?? '-',
                $problem['status'],
                Str::limit($problem['reason'], 100),
            ], $visible)
        );
    }
}
