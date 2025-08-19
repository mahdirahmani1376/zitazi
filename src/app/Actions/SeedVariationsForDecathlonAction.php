<?php

namespace App\Actions;

use App\Exceptions\UnProcessableResponseException;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;
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
