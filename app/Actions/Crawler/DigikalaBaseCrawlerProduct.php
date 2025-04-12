<?php

namespace App\Actions\Crawler;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCompare;
use Illuminate\Support\Facades\Log;

class DigikalaBaseCrawlerProduct extends BaseCrawler implements ProductAbstractCrawler
{

    public function crawl(Product $product)
    {
        $digikalaUrl = "https://api.digikala.com/v2/product/$product->digikala_source/";

        try {
            $response = ($this->sendHttpRequestAction)('get', $digikalaUrl)->collect();

            $variants = collect(data_get($response, 'data.product.variants'))
                ->map(function ($item) {
                    $item['seller_id'] = data_get($item, 'seller.id');

                    return $item;
                })->keyBy('seller_id');

            $digiPrice = data_get($variants, '69.price.selling_price');
            $minDigiPrice = $variants->filter(function ($i) {
                return $i['seller_id'] != 69;
            })->pluck('price.selling_price')->min();

            $sellersCount = $variants->pluck('seller_id')->count();

            if (!$digiPrice) {
                $digiPrice = data_get($response, 'data.product.default_variant.price.selling_price');
                Log::info('zitazi_not_available', [
                    'url' => $digikalaUrl,
                ]);
            }

            if ($sellersCount > 1) {
                if ($product->belongsToTrendyol()) {
                    $product->min_price = $product->price * Currency::syncTryRate() * 1.2;
                    $product->update();
                }

                $zitazi_digikala_price_recommend = $minDigiPrice * (99.5 / 100);

                if (!empty($product->min_price)) {
                    if ($zitazi_digikala_price_recommend < $product->min_price) {
                        $zitazi_digikala_price_recommend = $product->min_price;
                    }
                }

                $zitazi_digikala_price_recommend = floor($zitazi_digikala_price_recommend / 10000) * 10000;

            }

            $digiPrice = $digiPrice / 10;
            $minDigiPrice = $minDigiPrice / 10;
            $zitazi_digikala_price_recommend = $zitazi_digikala_price_recommend / 10;

            ProductCompare::updateOrCreate(
                [
                    'product_id' => $product->id,
                ],
                [
                    'zitazi_digi_ratio' => !empty($minDigiPrice) ? $digiPrice / $minDigiPrice : null,
                    'zitazi_digikala_price_recommend' => $zitazi_digikala_price_recommend,
                    'digikala_zitazi_price' => $digiPrice,
                    'digikala_min_price' => $minDigiPrice,
                ]
            );

        } catch (\Exception $e) {
            Log::error('error_digi_fetch' . $product->id, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
