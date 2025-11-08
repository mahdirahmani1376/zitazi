<?php

namespace App\Actions;

use App\DTO\ZitaziUpdateDTO;
use App\Jobs\SyncZitaziJob;
use App\Models\Currency;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Variation;
use Illuminate\Support\Facades\Log;

class SeedVariationsForDecathlonAction
{
    public function execute($result, $sync = false)
    {
        $product = Product::find($result['product_id']);

        $variationsRawData = $result['variations'];
        $itemType = Product::PRODUCT_UPDATE;

        if (count($variationsRawData) > 1) {
            $itemType = Product::VARIATION_UPDATE;
        }

        $availableVariations = [];
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
                'status' => Variation::AVAILABLE,
            ];

            $availableVariations[] = $variationRawData['sku'];

            $variation = Variation::updateOrCreate([
                'sku' => $variationRawData['sku'],
            ], $createData);

            if ($sync) {
                $updateData = ZitaziUpdateDTO::createFromArray([
                    'stock_quantity' => $variation->stock,
                    'price' => $variation->rial_price
                ]);
                SyncZitaziJob::dispatch($variation, $updateData);
            }

            $oldStock = $variation->stock;
            $oldPrice = $variation->rial_price;

            Log::info('decathlon-variation-updated', [
                'variation_id' => $variation->id,
                'data' => $createData,
            ]);
            if ($oldStock != $variation->stock || $oldPrice != $variation->rial_price) {
                $data = [
                    'old_stock' => $oldStock,
                    'new_stock' => $variation->stock,
                    'old_price' => $oldPrice,
                    'new_price' => $variation->rial_price,
                    'variation_own_id' => $variation->own_id,
                    'product_own_id' => $variation->product->own_id,
                ];

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

        $unavailableOnSourceSiteVariations = Variation::query()
            ->whereNotIn('sku', $availableVariations)
            ->where('product_id', $product->id)
            ->where('source', Product::SOURCE_DECATHLON)
            ->get();

        $unavailableOnSourceSiteVariations->each(function (Variation $variation) use ($sync) {
            if ($sync) {
                $updateData = ZitaziUpdateDTO::createFromArray([
                    'stock_quantity' => 0,
                    'price' => $variation->rial_price
                ]);
                SyncZitaziJob::dispatch($variation, $updateData);
            }

            $variation->update([
                'status' => Variation::UNAVAILABLE_ON_SOURCE_SITE,
            ]);
        }
        );


    }
}
