<?php

namespace App\Actions;

use App\Exceptions\UnProcessableResponseException;
use App\Models\Currency;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Variation;
use Illuminate\Support\Facades\Log;
use Opcodes\LogViewer\Facades\Cache;

class SeedVariationsForDecathlonAction
{
    public function execute($productId)
    {
        $product = Product::find($productId);
        $cacheKey = md5('response' . $product->id);
        $response = Cache::get($cacheKey);
        if (!$response) {
            throw UnProcessableResponseException::make('no cache key found for product:' . $product->id);
        }

        $variationsRawData = $response['variations'];
        $itemType = Product::PRODUCT_UPDATE;

        if (count($variationsRawData) > 1) {
            $itemType = Product::VARIATION_UPDATE;
        }

        foreach ($variationsRawData as $variationRawData) {
            $price = $variationRawData['price'];
            $rialPrice = Currency::convertToRial($price) * $product->getRatio();

            $createData = [
                'product_id' => $product->id,
                'sku' => $variationRawData['sku'],
                'price' => $price,
                'url' => $variationRawData['url'],
                'stock' => $variationRawData['stock'],
                'size' => $variationRawData['size'],
                'rial_price' => $rialPrice,
                'source' => Product::SOURCE_DECATHLON,
                'item_type' => $itemType,
            ];

            $variation = Variation::updateOrCreate([
                'sku' => $variationRawData['sku'],
            ], $createData);

            $oldStock = $variation->stock;
            $oldPrice = $variation->rial_price;

            if ($oldStock != $variation->stock || $oldPrice != $variation->rial_price) {
                $data = [
                    'old_stock' => $oldStock,
                    'new_stock' => $variation->stock,
                    'old_price' => $oldPrice,
                    'new_price' => $variation->rial_price,
                    'variation_own_id' => $variation->own_id,
                    'product_own_id' => $variation->product->own_id,
                ];

                Log::info('decathlon-variation-updated', [
                    'variation_id' => $variation->id,
                    'data' => $data,
                ]);
                SyncLog::create($data);
            }

        }

        $defaultVariation = $product->defaultVariation();

        if (!empty($defaultVariation)) {
            $price = $defaultVariation->price;
            $rialPrice = $defaultVariation->rial_price;
            $minPrice = $rialPrice * 1.2;
            $stock = $defaultVariation->stock;

            $product->update([
                'min_price' => $minPrice,
                'rial_price' => $rialPrice,
                'price' => $price,
                'stock' => $stock,
            ]);
        }
    }
}
