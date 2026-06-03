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
        $products = Product::query()
            ->whereNot('trendyol_source', '=', '')
            ->limit(20)
            ->get();

        foreach ($products as $product) {
            /** @var Product $product */
            if ($product->belongsToTrendyol()) {
                $product->setTrendyolFullUrl();
            }

            Redis::rPush(
                'scrape_product',
                json_encode([
                    'product' => $product->toArray(),
                    'sync' => false
                ])
            );
        }
    }
}
