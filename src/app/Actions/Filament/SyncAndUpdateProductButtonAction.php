<?php

namespace App\Actions\Filament;

use App\Jobs\SendScrapeMessageJob;
use App\Models\Product;

class SyncAndUpdateProductButtonAction
{
    public static function execute(Product $product)
    {
        SendScrapeMessageJob::dispatch($product);

        return [
            'success' => true,
        ];
    }
}
