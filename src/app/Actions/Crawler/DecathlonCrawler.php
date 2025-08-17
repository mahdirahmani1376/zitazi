<?php

namespace App\Actions\Crawler;

use App\Actions\HttpService;
use App\DTO\ZitaziUpdateDTO;
use App\Exceptions\UnProcessableResponseException;
use App\Models\Currency;
use App\Models\Variation;
use Exception;
use Illuminate\Support\Facades\Log;

class DecathlonCrawler extends BaseVariationCrawler implements VariationAbstractCrawler
{
    public function crawl(Variation $variation)
    {
        try {
            $response = HttpService::getDecathlonData($variation->url);
        } catch (Exception $exception) {
            $this->logErrorAndSyncVariation($variation);
            throw UnProcessableResponseException::make($exception->getMessage());
        }

        $data = collect($response['body']);
        if (empty($data)) {
            return $this->logErrorAndSyncVariation($variation);
        }

        $variations = collect($data)->keyBy('sku');
        if (!(isset($variations[$variation['sku']]))) {
            Log::error('sync-variations-action-sku-not-found', [
                'sku' => $variation['sku'],
                'variation_url' => $variation->url,
                'response_body' => $response['body'],
            ]);
            $this->logErrorAndSyncVariation($variation, Variation::UNAVAILABLE);
            throw UnProcessableResponseException::make('sku-not-found-decathlon');
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
            'status' => Variation::AVAILABLE,
        ];

        $this->updateVariationAndLog($variation, $data);

        $dto = ZitaziUpdateDTO::createFromArray([
            'price' => $rialPrice,
            'stock_quantity' => $stock,
        ]);


        $this->syncZitazi($variation, $dto);
    }

    public function supports(Variation $variation): bool
    {
        return !empty($variation->url);
    }

    private function logErrorAndSyncVariation(Variation $variation, $status = Variation::GENERAL_ERROR): bool
    {
        $data = [
            'stock' => 0,
            'status' => $status,
        ];

        $this->updateVariationAndLog($variation, $data);

        $dto = ZitaziUpdateDTO::createFromArray([
            'price' => null,
            'stock_quantity' => 0,
        ]);

        $this->syncZitazi($variation, $dto);

        return false;
    }
}
