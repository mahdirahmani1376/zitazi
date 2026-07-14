<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class BulkScrapeProductsCommand extends Command
{
    protected $signature = 'app:bulk-scrape';

    protected $description = 'Command description';

    public function handle(): void
    {
        Redis::pipeline(function ($pipe) {
            foreach (Product::query()->whereNotNull('trendyol_source')->cursor() as $product) {
                $product->setTrendyolFullUrl();
                $pipe->rpush(
                    'scrape_product',
                    json_encode([
                        'product' => $product->toArray(),
                        'bulk' => true,
                    ])
                );
            }

            foreach (Product::query()->whereNotNull('decathlon_url')->cursor() as $product) {
                $pipe->rpush(
                    'scrape_product',
                    json_encode([
                        'product' => $product->toArray(),
                        'bulk' => true,
                    ])
                );
            }
        });
    }
}
