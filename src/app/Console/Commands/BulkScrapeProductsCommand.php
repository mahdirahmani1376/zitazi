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
            ->get();

        /** @var Product $product */
        foreach ($products as $product) {
            if (empty($product->trendyol_source) && empty($product->decathlon_url)) {
                continue;
            }

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
