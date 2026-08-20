<?php

namespace App\Jobs;

use App\Enums\SyncStatusEnum;
use App\Models\Product;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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
        $key = null;

        if ($product->belongsToTrendyol()) {
            $product->setTrendyolFullUrl();
            if (empty($product->full_url)) {
                Log::error('no full_url for product', [
                    'product_id' => $product->id,
                    'trendyol_source' => $product->trendyol_source
                ]);
                return;
            }
            $key = config('queue.TR_QUEUE_IN');
        } elseif ($product->belongsToDecalthon()) {
            $key = config('queue.DE_QUEUE_IN');

        }

        if (!$key) {
            Log::error('product does not have source', [
                'product_id' => $product->id,
            ]);
            return;
        }

        $product->setSyncStatus(SyncStatusEnum::ENQUEUED);

        Redis::lPush(
            $key,
            json_encode([
                'product' => $product->toArray(),
                'bulk' => false
            ])
        );

    }
}
