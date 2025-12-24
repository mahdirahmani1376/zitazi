<?php

namespace App\Actions\Filament;

use App\Jobs\SeedVariationsForProductJob;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

class SyncAndUpdateProductButtonAction
{
    public static function execute(Product $product)
    {
        info('sync button clicked for product: ' . $product->id);
        if ($product->belongsToDecalthon()) {
            $response = Http::post('172.17.0.1:3000/scrape', $product);
            if (!$response->successful()) {
                return [
                    'success' => false,
                ];
            }
            return [
                'success' => true,
            ];
        }

        SeedVariationsForProductJob::dispatchSync($product, true);

        return [
            'success' => true,
        ];
    }
}
