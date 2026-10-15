<?php

namespace App\Services;

use App\Actions\LogManager;
use App\DTO\ZitaziUpdateDTO;
use App\Enums\SyncStatusEnum;
use App\Jobs\SyncZitaziJob;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class TorobService
{
    public function processResponse(Product $product, array $response): void
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
                        'rival_min_price' => $minPrice
                    ]);

                } else {
                    LogManager::logProduct($product, 'zitazi has min price', [
                        'shop_name' => $minShop['shop_name'],
                        'shop_price' => $minPrice
                    ]);

                    $product->update([
                        'rival_min_price' => null
                    ]);
                }
            }


            $product->setSyncStatus(SyncStatusEnum::SUCCESSFUL_DATA_FETCH_TOROB);

        }
    }

    public function updateProductVariationsAndSync(Product $product, bool $bulk = false): void
    {
        $queue = $bulk ? 'bulk-sync-products' : 'sync-products';

        $rivalMinPrice = $product->rival_min_price;

        if ($rivalMinPrice) {
            $lowestPossiblePrice = $product->min_price;

            $priceDecreaseStep = $rivalMinPrice * (99.5 / 100);

            $recommendedPrice = (int)(floor($priceDecreaseStep / 1000) * 1000);

            if ($recommendedPrice < $lowestPossiblePrice) {
                return;
            }

            foreach ($product->variations as $variation) {
                $originalPrice = $variation->rial_price;
                $variation->update([
                    'rial_price' => $recommendedPrice
                ]);

                LogManager::logVariation($variation, 'torob decrease step', [
                    'original_price' => $originalPrice,
                    'decreased_price' => $recommendedPrice,
                    'lowest_possible_price' => $lowestPossiblePrice,
                    'rival_price' => $product->rival_min_price
                ]);

                $updateData = ZitaziUpdateDTO::createFromArray([
                    'stock_quantity' => $variation->stock,
                    'price' => $recommendedPrice
                ]);

                SyncZitaziJob::dispatch($variation, $updateData)->onQueue($queue);
            }
        }
    }
}
