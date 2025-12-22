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

        $woocommerce = WoocommerceService::getClient($variation->base_source);
        $response = $woocommerce->get($url);

        LogManager::logVariation($variation, 'data fetch from source', [
            'response' => [
                'price' => data_get($response, 'price'),
                'sale_price' => data_get($response, 'sale_price'),
                'regular_price' => data_get($response, 'regular_price'),
                'stock_quantity' => data_get($response, 'stock_quantity'),
                'stock_status' => data_get($response, 'stock_status'),
                'zitazi_id' => data_get($response, 'id'),
            ]
        ]);
    }
}
