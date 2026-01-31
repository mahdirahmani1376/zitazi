<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Console\Command;

class BatchSyncZitaziProductsCommand extends Command
{
    protected $signature = 'batch:sync-zitazi-products';

    protected $description = 'Command description';

    public function handle(): void
    {
        $currency = Currency::syncTryRate();

        $variationIds = Variation::query()
            ->where('base_source', Product::ZITAZI)
            ->where('item_type', Product::VARIATION_UPDATE)
            ->get()
            ->pluck('id')
            ->unique();

        dd($variationIds);

//        Product::where([
//            'own_id' => 789593
//        ])->each(function (Product $product) use ($currency) {
//            $body = [];
//            foreach ($product->variations as $variation) {
//                if ($variation->status == Variation::AVAILABLE) {
//                    $stock = $variation->stock;
//                    $price = $variation->rial_price;
//
//                    if ($stock == 0) {
//                        $otherSource = null;
//                        if ($variation->source === Product::SOURCE_TRENDYOL) {
//                            $otherSource = Product::SOURCE_DECATHLON;
//                        } elseif ($variation->source === Product::SOURCE_DECATHLON) {
//                            $otherSource = Product::SOURCE_TRENDYOL;
//                        }
//
//                        if (!empty($otherSource)) {
//                            if ($variation->item_type === Product::VARIATION_UPDATE) {
//                                $otherSellerVariant =
//                                    Variation::query()
//                                        ->where('own_id', $variation->own_id)
//                                        ->whereNot('id', $variation->id)
//                                        ->where('source', $otherSource)
//                                        ->where('item_type', Product::VARIATION_UPDATE)
//                                        ->first();
//                            } else {
//                                $otherSellerVariant =
//                                    Variation::query()
//                                        ->where('product_id', $variation->product_id)
//                                        ->whereNot('id', $variation->id)
//                                        ->where('source', $otherSource)
//                                        ->where('item_type', Product::VARIATION_UPDATE)
//                                        ->first();
//                            }
//
//
//                            if (!empty($otherSellerVariant)) {
//                                $stock = $otherSellerVariant->stock;
//                                $price = $otherSellerVariant->rial_price;
//                            }
//                        }
//
//                    }
//
//                    $updateData = ZitaziUpdateDTO::createFromArray([
//                        'stock_quantity' => $stock,
//                        'price' => $price,
//                    ]);
//                }
//                else {
//                    $updateData = ZitaziUpdateDTO::createFromArray([
//                        'stock_quantity' => 0,
//                    ]);
//                }
//
//                if ($variation->item_type === Product::PRODUCT_UPDATE || $product->variations()->)
//            }
//
//            if (!empty($body))
//            {
//                $body = [
//                    'update' => $body
//                ];
//
//                try {
//                    $url = "/products/{$product->own_id}/variations/batch";
//                    $woocommerce = WoocommerceService::getClient($product->base_source);
//                    $response = $woocommerce->post($url, $body);
//                    LogManager::logProduct($product, 'update success product', [
//                        'product_id' => $product->id,
//                        'response' => json_encode($response)
//                    ]);
//                }
//                catch (\Exception $e) {
//                    LogManager::logProduct($product, 'batch sync error product', [
//                        'error' => $e->getMessage(),
//                        'product_id' => $product->id,
//                    ]);
//                    foreach ($product->variations as $variation)
//                    {
//                        $variation->update([
//                            'status' => Variation::UNAVAILABLE_ON_ZITAZI,
//                        ]);
//                    }
//                }
//            }
//        });
    }
//
//    function updateSingleProduct(mixed $variation)
//    {
//        SyncZitaziJob::dispatch($variation, $updateData);
//    }
}
