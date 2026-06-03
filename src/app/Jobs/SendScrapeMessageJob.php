<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class SendScrapeMessageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Product $product
    )
    {
    }

    public function handle(): void
    {
        $product = $this->product;
        if ($product->belongsToTrendyol()) {
            $product->setTrendyolFullUrl();
        }

        Redis::lPush(
            'scrape_product',
            json_encode([
                'product' => $product->toArray(),
                'sync' => true
            ])
        );
    }
}
