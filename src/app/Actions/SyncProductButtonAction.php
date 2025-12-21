<?php

namespace App\Actions;

use App\Jobs\SeedVariationsForProductJob;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

class SyncProductButtonAction
{
    public static function execute(Product $product)
    {
        info('stated sync for' . $product->id);

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

        SeedVariationsForProductJob::dispatch($product, true);

        return [
            'success' => true,
        ];
    }
}
