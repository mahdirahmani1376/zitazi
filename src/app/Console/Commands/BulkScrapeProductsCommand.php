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
        foreach (Product::where('id', 3)->get() as $product) {
            $response = Redis::rPush(
                'scrape_bulk_product',
                json_encode([
                    'product' => $product->toArray()
                ])
            );
            dump($response);
        }
    }
}
