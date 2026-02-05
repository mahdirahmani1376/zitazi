<?php

namespace App\Actions\Filament;

use App\Jobs\SeedVariationsForProductJob;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

class SyncAndUpdateProductButtonAction
{
    public static function execute(Product $product, $bulk = false)
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
        if ($product->belongsToTrendyol()) {
            $url = 'https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/product-detail/content';

            $params = http_build_query([
                'contentId' => $product->getTrendyolContentId(),
                'merchantId' => $product->getTrendyolMerchantId(),
            ]);

            $product->full_url = $url . '?' . $params;

            $response = Http::post('172.17.0.1:3000/scrape-tr', $product);
            if (!$response->successful()) {
                return [
                    'success' => false,
                ];
            }
            return [
                'success' => true,
            ];
        } else {
            if ($bulk) {
                SeedVariationsForProductJob::dispatch($product, true);
            } else {
                SeedVariationsForProductJob::dispatchSync($product, true);
                SendUpdateRequestForProductAction::execute($product);
            }
        }

        return [
            'success' => true,
        ];
    }
}
