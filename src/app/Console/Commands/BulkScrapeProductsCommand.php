<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BulkScrapeProductsCommand extends Command
{
    protected $signature = 'app:bulk-scrape {--source=}';

    protected $description = 'source options:[tr,de]';

    public function handle(): void
    {
        match ($this->option('source')) {
            'tr' => $this->bulkSyncTrendyol(),
            'de' => $this->bulkSyncDecathlon(),
            default => $this->bulkSyncAll(),
        };
    }

    /**
     * @return void
     */
    public function bulkSyncTrendyol(): void
    {
        dump('tren');
//        Redis::pipeline(function ($pipe) {
//            foreach (Product::query()
//                         ->whereNotNull('trendyol_source')
//                         ->whereRaw("TRIM(trendyol_source) != ''")
//                         ->cursor() as $product) {
//                $product->setTrendyolFullUrl();
//
//                if (empty($product->full_url)) {
//                    Log::error('no full_url for product', [
//                        'product_id' => $product->id,
//                        'trendyol_source' => $product->trendyol_source
//                    ]);
//                    return;
//                }
//
//                $product->setSyncStatus(SyncStatusEnum::ENQUEUED, false);
//
//                LogManager::logProduct($product, 'product enqueued for batch processing');
//
//                $pipe->rpush(
//                    config('queue.TR_QUEUE_IN'),
//                    json_encode([
//                        'product' => $product->only([
//                            'id',
//                            'full_url'
//                        ]),
//                        'bulk' => true,
//                    ])
//                );
//            }
//        });
    }

    /**
     * @return void
     */
    public function bulkSyncDecathlon(): void
    {
        dump('de');
//        Redis::pipeline(function ($pipe) {
//            foreach (Product::query()
//                         ->whereNotNull('decathlon_url')
//                         ->whereRaw("TRIM(decathlon_url) != ''")
//                         ->cursor() as $product) {
//
//                $product->setSyncStatus(SyncStatusEnum::ENQUEUED, false);
//
//                LogManager::logProduct($product, 'product enqueued for batch processing');
//
//                $pipe->rpush(
//                    config('queue.DE_QUEUE_IN'),
//                    json_encode([
//                        'product' => $product->only([
//                            'decathlon_url',
//                            'id'
//                        ]),
//                        'bulk' => true,
//                    ])
//                );
//            }
//        });
    }

    private function bulkSyncAll(): void
    {
        dump('all');

        $this->bulkSyncDecathlon();
        $this->bulkSyncTrendyol();
    }
}
