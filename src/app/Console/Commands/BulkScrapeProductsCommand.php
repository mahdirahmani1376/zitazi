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
            foreach (Product::query()->limit(1)->cursor() as $product) {
                if (!$product->belongsToTrendyol() && !$product->belongsToDecalthon()) {
                    continue;
                }

                dump($product->toArray());

                if ($product->belongsToTrendyol()) {
                    $product->setTrendyolFullUrl();
                }

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
