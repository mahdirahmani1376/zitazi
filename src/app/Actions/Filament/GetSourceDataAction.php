<?php

namespace App\Actions\Filament;

use App\Actions\LogManager;
use App\Models\Product;
use App\Models\Variation;
use App\Services\WoocommerceService;

class GetSourceDataAction
{
    public static function executeVariation(Variation $variation): void
    {
        if ($variation->item_type == Product::PRODUCT_UPDATE and empty($variation->own_id)) {
            $url = "products/{$variation->product->own_id}";
        } elseif (!empty($variation->own_id)) {
            $url = "products/{$variation->product->own_id}/variations/{$variation->own_id}";
        } else {
            return;
        }

        $response = WoocommerceService::sendRequest($url, [], 'get', $variation->base_source);

        LogManager::logVariation($variation, 'data fetch from source', [
            'response' => $response->body(),
            'code' => $response->getStatusCode()
        ]);
    }
}
