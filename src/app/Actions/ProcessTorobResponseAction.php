<?php

namespace App\Actions;

use App\Enums\SyncStatusEnum;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ProcessTorobResponseAction
{
    public function execute(Product $product, array $response): void
    {
        if ($response['success'] === false) {
            Log::error('torob sync error', [
                'product_id' => $product->id,
                'response' => $response
            ]);

            $product->setSyncStatus(SyncStatusEnum::FAILED_DATA_FETCH_TOROB);

            LogManager::logProduct($product, 'error fetching torob data', $response);

        } else {
            $minShop = collect(
                data_get($response, 'response_data.result')
            )
                ->filter(function ($item) {
                    return $item['price'] != 0;
                })
                ->sortBy('price')
                ->first();

            if (empty($minShop['price'])) {
                LogManager::logProduct($product, 'no min shop found', [
                    'shop_name' => $minShop['shop_name'],
                ]);

                $product->update([
                    'min_price' => null
                ]);
            } else {
                $minPrice = $minShop['price'];
                $rivalShop = $minShop['shop_id'];

                if ($rivalShop !== config('services.zitazi.torob_shop_id')) {
                    LogManager::logProduct($product, 'rival shop found', [
                        'shop_name' => $minShop['shop_name'],
                        'shop_price' => $minPrice
                    ]);

                    $product->update([
                        'min_price' => $minPrice
                    ]);

                } else {
                    LogManager::logProduct($product, 'zitazi has min price', [
                        'shop_name' => $minShop['shop_name'],
                        'shop_price' => $minPrice
                    ]);

                    $product->update([
                        'min_price' => null
                    ]);
                }
            }


            $product->setSyncStatus(SyncStatusEnum::SUCCESSFUL_DATA_FETCH_TOROB);

        }
    }
}
