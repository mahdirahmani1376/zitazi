<?php

namespace App\Actions\Crawler;

use App\Actions\HttpService;
use App\Actions\LogManager;
use App\Exceptions\UnProcessableResponseException;
use App\Models\Currency;
use App\Models\Variation;
use Symfony\Component\DomCrawler\Crawler;

class EleleCrawler extends BaseVariationCrawler implements VariationAbstractCrawler
{
    public function crawl(Variation $variation): void
    {
        try {
            $this->process($variation);
        } catch (\Exception $exception) {
            $this->logErrorAndSyncVariation($variation);
            throw UnProcessableResponseException::make('elele sync error exception: ' . $exception->getMessage());
        }
    }

    public function process(Variation $variation)
    {
        $response = HttpService::getEleleData($variation->url);
        $element = 'script[type="application/ld+json"]';

        $crawler = new Crawler($response);
        $dom = $crawler->filter($element)->first();

        $data = json_decode($dom->text(), true);
        $price = data_get($data, 'offers.price');
        if (empty($price)) {
            LogManager::logVariation($variation, 'elele_parse_error_product', [
                'url' => $variation->url,
            ]);
            throw UnProcessableResponseException::make("elele_parse_error_product");
        }

        $rialPrice = Currency::convertToRial($price) * $variation->product->getRatio();
        $stock = data_get($data, 'offers.availability') == 'InStock' ? 88 : 0;

        $data = [
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice,
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
        return !empty($variation->url);
    }
}
