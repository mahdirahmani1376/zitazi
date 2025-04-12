<?php

namespace App\Actions\Crawler;

use App\DTO\ZitaziUpdateDTO;
use Symfony\Component\DomCrawler\Crawler;

class TrendyolCrawler extends BaseProductCrawler implements ProductAbstractCrawler
{
    public function crawl($product)
    {
        $response = ($this->sendHttpRequestAction)('get', $product->trendyol_source)->body();
        $crawler = new Crawler($response);

        $price = null;
        $stock = 0;
        $rialPrice = null;

        foreach (range(2, 5) as $i) {
            $priceElement = $crawler->filter("body > script:nth-child($i)")->first();
            if ($priceElement->count() > 0) {
                $pattern = '/"discountedPrice"\s*:\s*\{.*?\}/';
                $price = preg_match($pattern, $priceElement->text(), $matches);
                if ($matches) {
                    $json = json_decode('{' . $matches[0] . '}', true);
                    $price = $json['discountedPrice']['value'];
                    $price = (int)str_replace(',', '.', trim($price));
                    $rialPrice = $this->rate * $price;
                    $rialPrice = $rialPrice * $this->getProfitRatioForProduct($product);
                    $rialPrice = floor($rialPrice / 10000) * 10000;
                    break;
                }
            }
        }

        $stock = $crawler->filter('div.product-button-container .buy-now-button-text')->first();
        if ($stock->count() > 0) {
            $stock = 88;
        } else {
            $stock = 0;
        }

        if (
            $stock == 0
            && $product->belongsToDecalthon()
            && $product->decathlonVariation()->exists()
        ) {
            [
                $price,
                $stock,
                $rialPrice,
            ] = ($this->syncVariationAction)->getVariationData($product->decathlonVariation);
        }

        if (empty($price)) {
            $stock = 0;
            $price = null;
        }

        $data = [
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice,
        ];

        $updateData = ZitaziUpdateDTO::createFromArray([
            'price' => $rialPrice,
            'stock_quantity' => $stock,
        ]);

        $this->updateAndLogProduct($product, $data);

        if (!$product->belongsToIran()) {
            $this->updateZitazi($product, $updateData);
        }
    }
}
