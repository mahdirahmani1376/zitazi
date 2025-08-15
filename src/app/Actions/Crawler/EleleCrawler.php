<?php

namespace App\Actions\Crawler;

use App\DTO\ZitaziUpdateDTO;
use App\Exceptions\UnProcessableResponseException;
use App\Models\Currency;
use App\Models\Product;
use Symfony\Component\DomCrawler\Crawler;

class EleleCrawler extends BaseCrawler implements ProductAbstractCrawler
{
    public function crawl(Product $product): void
    {
        $response = $this->httpService->sendWithCache('get', $product->elele_source);

        $element = 'script[type="application/ld+json"]';

        $crawler = new Crawler($response);
        $dom = $crawler->filter($element)->first();

        $data = json_decode($dom->text(), true);
        $price = data_get($data, 'offers.price');
        if (empty($price)) {
            throw UnProcessableResponseException::make("elele_parse_error_product:{$product->id}");
        }

        $rialPrice = Currency::convertToRial($price) * $product->getRatio();
        $stock = data_get($data, 'offers.availability') == 'InStock' ? 88 : 0;

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
