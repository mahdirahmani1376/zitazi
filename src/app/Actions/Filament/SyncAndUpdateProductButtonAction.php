<?php

namespace App\Actions\Filament;

use App\Models\Product;
use App\Services\ScraperQueueService;

class SyncAndUpdateProductButtonAction
{
    public static function execute(Product $product): array
    {
        app(ScraperQueueService::class)->sendToAllScraperQueues($product);

        return [
            'success' => true,
        ];
    }
}
