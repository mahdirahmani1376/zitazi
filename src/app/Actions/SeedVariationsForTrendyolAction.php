<?php

namespace App\Actions;

use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;
use Exception;

class SeedVariationsForTrendyolAction
{
    public function execute($response)
    {
        $data = data_get($response, 'response.result.variants', []);
        $product = Product::find($response['product_id']);
        if (empty($data)) {
            LogManager::LogProduct($product, 'no variants found for product', [
                'product_id' => $product->id,
                'product_own_id' => $product->own_id,
            ]);
            return;
        }

        $itemType = count($data) > 1 ? Product::VARIATION_UPDATE : Product::PRODUCT_UPDATE;

        try {
            $availableVariations = [];
            foreach ($data as $item) {
                Variation::updateOrCreate([
                    'item_number' => $item['itemNumber']
                ], [
                    'size' => $item['value'] ?? null,
                    'price' => $price = $item['price']['value'],
                    'rial_price' => Currency::convertToRial($price) * $product->getRatio(),
                    'stock' => !empty($item['inStock']) ? 88 : 0,
                    'barcode' => $item['barcode'],
                    'color' => $item['value'],
                    'url' => Product::PRODUCT_UPDATE ? $product->trendyol_source : data_get($item, 'merchantListing.otherMerchants.0.url', $product->trendyol_source),
                    'sku' => $response['result']['id'] ?? null,
                    'product_id' => $product->id,
                    'source' => Product::SOURCE_TRENDYOL,
                    'item_type' => $itemType,
                    'status' => Variation::AVAILABLE,
                    'item_number' => $item['itemNumber'],
                    'base_source' => $product->base_source,
                ]);

                $availableVariations[] = $item['itemNumber'];
            }

            $unavailableOnSourceSiteVariations = Variation::query()
                ->whereNotIn('item_number', $availableVariations)
                ->where('product_id', $product->id)
                ->where('source', Product::SOURCE_TRENDYOL)
                ->get();

            $unavailableOnSourceSiteVariations->each(fn(Variation $variation) => $variation->update([
                'status' => Variation::UNAVAILABLE_ON_SOURCE_SITE,
            ]));
        } catch (Exception $e) {
            dump($e->getMessage(), $product->id);
            LogManager::LogProduct($product, 'error-seed-variations', [
                'product_id' => $product->id,
                'product_own_id' => $product->own_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
