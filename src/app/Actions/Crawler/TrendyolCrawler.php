<?php

namespace App\Actions\Crawler;

use App\DTO\ZitaziUpdateDTO;
use App\Exceptions\UnProcessableResponseException;
use App\Models\Currency;
use App\Models\Product;
use Symfony\Component\DomCrawler\Crawler;

class TrendyolCrawler extends BaseCrawler implements ProductAbstractCrawler
{
    public function crawl($product)
    {
        $response = $this->sendHttpRequestAction->sendWithCache('get', $product->trendyol_source);

        $crawler = new Crawler($response);

        $price = null;
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
                    $rialPrice = Currency::convertToRial($price, $this->getProfitRatioForProduct($product));
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
            ] = app(DecathlonCrawler::class)->getVariationData($product->decathlonVariation);
        }

        if (empty($price)) {
            $updateData = ZitaziUpdateDTO::createFromArray([
                'stock_quantity' => 0,
            ]);
            $this->syncProductWithZitazi($product, $updateData);
            throw UnProcessableResponseException::make("failed_to_fetch_trendyol_price_for_product_{$product->id}");
        }

        $data = [
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice,
        ];


        $this->updateAndLogProduct($product, $data);

        $updateData = ZitaziUpdateDTO::createFromArray([
            'price' => $rialPrice,
            'stock_quantity' => $stock,
        ]);
        $this->syncProductWithZitazi($product, $updateData);
    }

    public function supports(Product $product): bool
    {
        return !empty($product->trendyol_source);
    }
}
