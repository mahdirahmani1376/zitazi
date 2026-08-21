<?php

namespace App\Console\Commands;

use App\Enums\SyncStatusEnum;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class BulkScrapeProductsCommand extends Command
{
    protected $signature = 'app:bulk-scrape';

    protected $description = 'source options:[tr,de]';

    public function handle(): void
    {
        Redis::pipeline(function ($pipe) {
            foreach (Product::query()
                         ->whereNotNull('trendyol_source')
                         ->whereRaw("TRIM(trendyol_source) != ''")
                         ->cursor() as $product) {
                $product->setTrendyolFullUrl();

                if (empty($product->full_url)) {
                    Log::error('bulk scrape log', [
                        'message' => 'no full_url for product',
                        'product_id' => $product->id,
                        'trendyol_source' => $product->trendyol_source
                    ]);
                    return;
                }

                $product->setSyncStatus(SyncStatusEnum::ENQUEUED);

                Log::info('bulk scrape log', [
                    'product_id' => $product->id,
                    'message' => 'product enqueued for batch processing',
                    'trendyol_source' => $product->trendyol_source
                ]);

                $pipe->rpush(
                    config('queue.TR_QUEUE_IN'),
                    json_encode([
                        'product' => $product->only([
                            'id',
                            'full_url'
                        ]),
                        'bulk' => true,
                    ])
                );
            }
        });

        Redis::pipeline(function ($pipe) {
            foreach (Product::query()
                         ->whereNotNull('decathlon_url')
                         ->whereRaw("TRIM(decathlon_url) != ''")
                         ->cursor() as $product) {

                $product->setSyncStatus(SyncStatusEnum::ENQUEUED);

                Log::info('bulk scrape log', [
                    'product_id' => $product->id,
                    'message' => 'product enqueued for batch processing',
                ]);

                $pipe->rpush(
                    config('queue.DE_QUEUE_IN'),
                    json_encode([
                        'product' => $product->only([
                            'decathlon_url',
                            'id'
                        ]),
                        'bulk' => true,
                    ])
                );
            }
        });

    }
}
