<?php

namespace App\Actions\Crawler;

use App\Actions\HttpService;
use App\Exceptions\UnProcessableResponseException;
use App\Models\Product;
use App\Models\Variation;
use Symfony\Component\DomCrawler\Crawler;

class SazKalaCrawler extends BaseVariationCrawler implements VariationAbstractCrawler
{
    public function crawl(Variation $variation): void
    {
        try {
            $this->process($variation);
        } catch (\Exception $exception) {
            $this->logErrorAndSyncVariation($variation);
            throw UnProcessableResponseException::make('sazkala sync error exception: ' . $exception->getMessage());
        }
    }

    public function process(Variation $variation)
    {
        $response = HttpService::getSazKalaData($variation->url);
        $crawler = new Crawler($response);
        $price = $crawler->filter('meta[property="product:price:amount"]')->first()->attr('content');
        $stock = $crawler->filter('meta[property="product:availability"]')->first()->attr('content');
        $stock = $stock === 'instock' ? 88 : 0;

        $data = [
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $price,
            'status' => Variation::AVAILABLE
        ];

        $this->updateVariationAndLog($variation, $data);

    }

    private function logErrorAndSyncVariation(Variation $variation): bool
    {
        $data = [
            'stock' => 0,
            'status' => Variation::UNAVAILABLE,
        ];

        $this->updateVariationAndLog($variation, $data);

        return false;
    }


    public function supports(Variation $variation): bool
    {
        return $variation->source == Product::SOURCE_SAZ_KALA;
    }
}
