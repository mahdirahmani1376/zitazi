<?php

namespace App\Actions\Crawler;

use App\DTO\ZitaziUpdateDTO;
use App\Exceptions\UnProcessableResponseException;
use App\Models\Product;
use Symfony\Component\DomCrawler\Crawler;

class EleleCrawlerManager extends CrawlerManager implements ProductAbstractCrawler
{
    public function crawl(Product $product): void
    {
        $response = ($this->sendHttpRequestAction)('get', $product->elele_source)->body();

        $price = null;
        $stock = 0;
        $rialPrice = null;

        $crawler = new Crawler($response);

        foreach (range(2, 5) as $i) {
            $dom = $crawler->filter("#formGlobal > script:nth-child($i)")->first();

            if ($dom->count() > 0) {
                preg_match('/"productPriceKDVIncluded":([0-9]+\.[0-9]+)/', $dom->text(), $matches);

                if (isset($matches[1])) {
                    $price = $matches[1];
                    $rialPrice = $price * $this->getProfitRatioForProduct($product) * $this->rate;
                    $rialPrice = floor($rialPrice / 10000) * 10000;
                    break;

                }
            }

            $stockElement = 'input.Addtobasket.button.btnAddBasketOnDetail';
            $stockResult = $crawler->filter($stockElement)->first();
            $stock = 0;
            if ($stockResult->count() > 0) {
                $stock = 88;
            }
        }

        if (empty($price)) {
            throw UnProcessableResponseException::make("elele_parse_error_product:{$product->id}");
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
            $this->syncProductWithZitazi($product, $updateData);
        }

    }

    public function supports(Product $product): bool
    {
        return !empty($product->elele_source);
    }
}
