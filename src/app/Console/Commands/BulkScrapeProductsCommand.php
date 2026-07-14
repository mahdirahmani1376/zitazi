<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class BulkScrapeProductsCommand extends Command
{
    protected $signature = 'app:bulk-scrape {--source=}';

    protected $description = 'source options:[tr,de]';

    public function handle(): void
    {
        Redis::pipeline(function ($pipe) {
            if ($this->option('source') === 'tr') {
                foreach (Product::query()
                             ->whereNotNull('trendyol_source')
                             ->whereRaw("TRIM(trendyol_source) != ''")
//                             ->limit(10)
                             ->cursor() as $product) {
                    $product->setTrendyolFullUrl();
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
            } else if ($this->option('source') === 'de') {
                foreach (Product::query()
                             ->whereNotNull('decathlon_url')
                             ->whereRaw("TRIM(decathlon_url) != ''")
//                             ->limit(5)
                             ->cursor() as $product) {
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
            }

        });
    }
}
