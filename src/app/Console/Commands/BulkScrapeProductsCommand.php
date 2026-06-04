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
            foreach (Product::query()->cursor() as $product) {
                if (!$product->belongsToTrendyol() && !$product->belongsToDecalthon()) {
                    continue;
                }

                if ($product->belongsToTrendyol()) {
                    $product->setTrendyolFullUrl();
                }

                $pipe->rpush(
                    'scrape_product',
                    json_encode([
                        'product' => [
                            'id' => $product->id,
                            'decathlon_url' => $product->decathlon_url,
                            'trendyol_source' => $product->trendyol_source,
                            'full_url' => $product->full_url
                        ],
                        'sync' => false,
                    ])
                );
            }
        });
    }
}
