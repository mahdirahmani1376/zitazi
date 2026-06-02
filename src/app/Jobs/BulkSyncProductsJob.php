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

class BulkSyncProductsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Product $product)
    {
    }

    public function handle(): void
    {
        $product = $this->product;

        if ($product->belongsToTrendyol()) {
            $product->setTrendyolFullUrl();
        }


        Redis::rpush(
            'scrape:product',
            json_encode([
                'job_id' => $this->batchId,
                'product' => $this->product,
            ])
        );
    }
}
