<?php

namespace App\Actions;

use App\Enums\SyncStatusEnum;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class SyncTorobAction
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
