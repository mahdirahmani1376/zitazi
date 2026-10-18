<?php

namespace App\Services;

use App\Actions\LogManager;
use App\Enums\SyncStatusEnum;
use App\Jobs\SyncWoocommerceProductJob;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TorobService
{
    public function processResponse(Product $product, array $response): void
    {
        if ($response['success'] === false) {
            Log::error('torob sync error', [
                'product_id' => $product->id,
                'response' => $response,
            ]);

            $product->setSyncStatus(SyncStatusEnum::FAILED_DATA_FETCH_TOROB);

            LogManager::logProduct($product, 'error fetching torob data', $response);

        }
        else {
            if (empty(data_get($response, 'response_data.result'))) {
                LogManager::logProduct($product, 'product has no seller in torob');
                $product->setSyncStatus(SyncStatusEnum::SUCCESSFUL_DATA_FETCH_TOROB);

                return;
            }

            $validShops = collect(
                data_get($response, 'response_data.result')
            )
                ->filter(function ($item) {
                    return $item['price'] != 0 && $item['is_price_unreliable'] != true;
                });

            if ($validShops->isEmpty()) {
                LogManager::logProduct($product, 'no valid shops found', $validShops->toArray());

                $product->update([
                    'rival_min_price' => null,
                ]);
            } else {
                $minShop = $validShops
                    ->sortBy('price')
                    ->first();

                $minPrice = $minShop['price'];
                $rivalShop = $minShop['shop_id'];

                if ($rivalShop !== config('services.zitazi.torob_shop_id')) {
                    LogManager::logProduct($product, 'rival shop found', [
                        'shop_name' => $minShop['shop_name'],
                        'shop_price' => $minPrice,
                    ]);

                    $product->update([
                        'rival_min_price' => $minPrice,
                    ]);

                } else {
                    LogManager::logProduct($product, 'zitazi has min price', [
                        'shop_name' => $minShop['shop_name'],
                        'shop_price' => $minPrice,
                    ]);

                    $product->update([
                        'rival_min_price' => null,
                    ]);
                }
            }

            $product->setSyncStatus(SyncStatusEnum::SUCCESSFUL_DATA_FETCH_TOROB);

        }
    }

    public function updateProductAndSync(Product $product, bool $bulk = false): void
    {
        $rivalMinPrice = $product->rival_min_price;

        if (! $rivalMinPrice) {
            return;
        }

        $lowestPossiblePrice = $product->min_price;

        $priceDecreaseStep = $rivalMinPrice * (99.5 / 100);

        $recommendedPrice = (int) (floor($priceDecreaseStep / 1000) * 1000);

        if ($recommendedPrice < $lowestPossiblePrice) {
            return;
        }

        $isSatreh = $product->base_source === Product::SATRE;

        $cacheKey = $isSatreh ? 'sync_satreh_enabled' : 'sync_zitazi_enabled';
        $configKey = $isSatreh ? 'services.satreh_sync_enabled' : 'services.zitazi_sync_enabled';

        if (! Cache::get($cacheKey, config($configKey))) {
            LogManager::logProduct($product, 'sync is disabled in config');
            $product->setSyncStatus(SyncStatusEnum::ENV_DISABLED);

            return;
        }

        if ($product->onPromotion()) {
            LogManager::logProduct($product, 'product on promotion');
            $product->setSyncStatus(SyncStatusEnum::VARIATION_HAS_PROMOTION_ACTIVE);

            return;
        }

        $queue = $bulk ? 'bulk-sync-products' : 'sync-products';

        SyncWoocommerceProductJob::dispatch($product->id, $recommendedPrice)->onQueue($queue);
    }
}
