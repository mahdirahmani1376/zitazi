<?php

namespace App\Services;

use App\Enums\SyncStatusEnum;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ScraperQueueService
{
    public string $queue = 'torob';

    public function sendToAllScraperQueues(Product $product): void
    {
        if ($product->belongsToTorob()) {
            $this->addToTorobQueue($product);
        } else {
            $this->addToForeignQueue($product);
        }
    }

    private function addToTorobQueue(Product $product): void
    {
        $key = config('queue.TOROB_QUEUE_IN');

        $data = json_encode([
            'product' => $product->getTorobQueueScrapeData(),
            'bulk' => false,
        ]);

        Redis::lPush($key, $data);
    }

    public function addToForeignQueue(Product $product): void
    {
        $key = null;
        $data = [];

        if ($product->belongsToTrendyol()) {
            if (empty($product->getTrendyolFullUrl())) {
                Log::error('no full_url for product', [
                    'product_id' => $product->id,
                    'trendyol_source' => $product->trendyol_source
                ]);
                return;
            }
            $key = config('queue.TR_QUEUE_IN');

            $data = json_encode([
                'product' => $product->getTrendyolQueueScrapeData(),
                'bulk' => false,
            ]);

        } elseif ($product->belongsToDecalthon()) {
            $key = config('queue.DE_QUEUE_IN');

            $data = json_encode([
                'product' => $product->only([
                    'decathlon_url',
                    'id'
                ]),
                'bulk' => false,
            ]);
        }

        if (!$key) {
            Log::error('product does not have source', [
                'product_id' => $product->id,
            ]);
            return;
        }

        $product->setSyncStatus(SyncStatusEnum::ENQUEUED);

        Redis::lPush($key, $data);
    }
}
