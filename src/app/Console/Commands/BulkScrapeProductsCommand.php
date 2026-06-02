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
        foreach (Product::all() as $product) {
            Redis::rPush(
                'scrape_bulk_product',
                json_encode([
                    'product' => $product->toArray()
                ])
            );
        }
    }
}
