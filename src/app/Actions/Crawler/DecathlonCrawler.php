<?php

namespace App\Actions\Crawler;

use App\DTO\ZitaziUpdateDTO;
use App\Models\Currency;
use App\Models\Variation;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class DecathlonCrawler extends BaseVariationCrawler implements VariationAbstractCrawler
{
    public function crawl(Variation $variation)
    {
        $response = $this->sendHttpRequestAction->sendWithCache('get', $variation->url);

        $element = 'script[type="application/ld+json"]';

        $crawler = new Crawler($response);
        $element = $crawler->filter($element)->first();

        $variations = [];
        if ($element->count() > 0) {

            $data = collect(json_decode($element->text(), true));
            $productId = data_get($data, 'productID');
            $offers = collect($data->get('offers'))->collapse();
            foreach ($offers as $offer) {
                $variationStock = data_get($offer, 'availability') == 'https://schema.org/InStock' ? 88 : 0;
                $variations[] = [
                    'product_id' => $productId,
                    'sku' => $offer['sku'] ?? null,
                    'price' => $offer['price'] ?? null,
                    'url' => $offer['url'] ?? null,
                    'stock' => $variationStock,
                ];
            }
        }

        $variations = collect($variations)->keyBy('sku');
        if (!(isset($variations[$variation['sku']]))) {
            Log::error('sync-variations-action-sku-not-found', [
                'sku' => $variation['sku'],
                'variation_url' => $variation->url,
            ]);

            return [
                'price' => null,
                'stock' => 0,
                'rial_price' => null,
            ];
        }

        $stock = data_get($variations, "{$variation->sku}.stock", 0);

        $price = (int)str_replace(',', '.', trim($variation['price']));
        $rialPrice = Currency::convertToRial($price) * $this->getProfitRatioForVariation($variation);

        if (empty($price) || empty($rialPrice)) {
            $stock = 0;
            $price = null;
            $rialPrice = null;
        }

        $data = [
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice,
        ];

        $this->updateVariationAndLog($variation, $data);

        $dto = ZitaziUpdateDTO::createFromArray([
            'price' => $rialPrice,
            'stock_quantity' => $stock,
        ]);

        $this->syncZitazi($variation, $dto);
    }

    public function getVariationData(Variation $variation)
    {
        $response = $this->sendHttpRequestAction->sendWithCache('get', $variation->url);

        $element = 'script[type="application/ld+json"]';

        $crawler = new Crawler($response);
        $element = $crawler->filter($element)->first();

        $variations = [];
        if ($element->count() > 0) {

            $data = collect(json_decode($element->text(), true));
            $productId = data_get($data, 'productID');
            $offers = collect($data->get('offers'))->collapse();
            foreach ($offers as $offer) {
                $variationStock = $offer['availability'] == 'https://schema.org/InStock' ? 88 : 0;
                $variations[] = [
                    'product_id' => $productId,
                    'sku' => $offer['sku'] ?? null,
                    'price' => $offer['price'] ?? null,
                    'url' => $offer['url'] ?? null,
                    'stock' => $variationStock,
                ];
            }
        }

        $variations = collect($variations)->keyBy('sku');
        if (!(isset($variations[$variation['sku']]))) {
            Log::error('sync-variations-action-sku-not-found', [
                'sku' => $variation['sku'],
                'variation_url' => $variation->url,
            ]);

            return [
                'price' => null,
                'stock' => 0,
                'rial_price' => null,
            ];
        }

        $stock = data_get($variations, "{$variation->sku}.stock", 0);

        $price = (int)str_replace(',', '.', trim($variation['price']));
        $rialPrice = Currency::convertToRial($price) * $this->getProfitRatioForVariation($variation);

        if (empty($price) || empty($rialPrice)) {
            $stock = 0;
            $price = null;
            $rialPrice = null;
        }

        $data = [
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice,
        ];

        return $data;
    }

    public function supports(Variation $variation): bool
    {
        return !empty($variation->url);
    }
}
