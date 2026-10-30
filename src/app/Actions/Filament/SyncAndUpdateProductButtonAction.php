<?php

namespace App\Actions\Filament;

use App\Models\Product;
use App\Services\ScraperQueueService;
use Illuminate\Support\Facades\Cache;

class SyncAndUpdateProductButtonAction
{
    public static function execute(Product $product): array
    {
        app(ScraperQueueService::class)->sendToAllScraperQueues($product);

        if (app()->environment('production')) {
            Cache::forget("torob:scrapeops:{$product->id}");
        }

        return [
            'success' => true,
        ];
    }
}
