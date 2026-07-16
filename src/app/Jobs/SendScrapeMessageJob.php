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
        $key = config('queue.DE_QUEUE_IN');

        if ($product->belongsToTrendyol()) {
            $product->setTrendyolFullUrl();
            $key = config('queue.TR_QUEUE_IN');
        }

        Redis::lPush(
            $key,
            json_encode([
                'product' => $product->toArray(),
                'bulk' => false
            ])
        );
    }
}
