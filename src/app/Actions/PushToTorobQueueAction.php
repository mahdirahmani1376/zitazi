<?php

namespace App\Actions;

use App\Models\Product;
use Illuminate\Support\Facades\Redis;

class PushToTorobQueueAction
{
    public static function execute(Product $product): void
    {
        if (!empty($product->getTorobMinPrice())) {

        } else {
            Redis::rpush(
                config('queue.TOROB_QUEUE_IN'),
                json_encode([
                    'product' => $product->getTorobQueueScrapeData(),
                    'bulk' => false,
                ])
            );
        }

    }
}
