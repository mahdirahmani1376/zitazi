<?php

namespace App\Actions\Crawler;

use App\Actions\LogManager;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCompare;

class TorobCrawler extends BaseCrawler implements ProductAbstractCrawler
{
    public function crawl($product): void
    {
        try {
            $this->compareProductWithOtherSellers($product);
        } catch (\Exception $e) {
            LogManager::logProduct($product, 'error_torob_fetch', [
                'error' => $e->getMessage(),
            ]);
        }

    }

    private function compareProductWithOtherSellers(Product $product): void
    {
        $zitaziTorobPrice = $product->min_price;
        $torobMinPrice = $product->rival_min_price;

        if ($product->belongsToTrendyol()) {
            $this->setMinPriceOfProductForTrendyol($product);
        }

        $zitaziTorobPriceRecommend = $this->getZitaziTorobPriceRecommend($torobMinPrice, $product);

        ProductCompare::updateOrCreate(
            [
                'product_id' => $product->id,
            ],
            [
                'zitazi_torob_price_recommend' => $zitaziTorobPriceRecommend,
                'zitazi_torob_ratio' => !empty($torobMinPrice) ? $zitaziTorobPrice / $torobMinPrice : null,
                'zitazi_torob_price' => $zitaziTorobPrice,
                'torob_min_price' => $torobMinPrice,
            ]
        );

        $this->syncProductWithZitazi($product, ZitaziUpdateDTO::createFromArray([
            'price' => '' . $zitaziTorobPriceRecommend,
        ]));
    }
    private function setMinPriceOfProductForTrendyol(Product $product): void
    {
        $minPrice = $product->price * Currency::syncTryRate() * 1.2;
        $product->min_price = floor($minPrice / 10000) * 10000;
        $product->update();
    }

    private function getZitaziTorobPriceRecommend(mixed $torobMinPrice, Product $product): int|float
    {
        $zitazi_torob_price_recommend = $torobMinPrice * (99.5 / 100);

        if (!empty($product->min_price)) {
            if ($zitazi_torob_price_recommend < $product->min_price) {
                $zitazi_torob_price_recommend = $product->min_price;
            }

        }
        return floor($zitazi_torob_price_recommend / 10000) * 10000;
    }
    public function supports(Product $product): bool
    {
        return !empty($product->rival_min_price);
    }

}
