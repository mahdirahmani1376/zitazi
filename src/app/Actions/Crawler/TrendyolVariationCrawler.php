<?php

namespace App\Actions\Crawler;

use App\Actions\HttpService;
use App\DTO\ZitaziUpdateDTO;
use App\Exceptions\UnProcessableResponseException;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;

class TrendyolVariationCrawler extends BaseVariationCrawler
{
    public function crawl(Variation $variation)
    {
        try {
            $response = HttpService::getTrendyolData($variation->product->getTrendyolContentId(), $variation->product->getTrendyolMerchantId());
            \Log::info('response variation', [
                'contentId' => $variation->product->getTrendyolContentId(),
                'merchantId' => $variation->product->getTrendyolMerchantId(),
                'response' => $response,
                'id' => $variation->id,
                'sku' => $variation->sku,
            ]);
        } catch (\Exception $e) {
            $this->logErrorAndSyncVariation($variation);
            throw UnProcessableResponseException::make('unprocessable-response-trendyol');
        }

        if (data_get($response, 'statusCode') === 404) {
            return $this->logErrorAndSyncVariation($variation, Variation::UNAVAILABLE_ON_SOURCE_SITE);
        }
        $data = collect($response['result']['variants'])->keyBy('itemNumber');

        $result = null;
        if (isset($variation->item_number) && $variation->item_type = Product::VARIATION_UPDATE) {
            $result = $data->get($variation->item_number);
        } elseif ($variation->item_type = Product::PRODUCT_UPDATE) {
            $result = collect($response['result']['variants']);
        } elseif (empty($result)) {
            $this->logErrorAndSyncVariation($variation, Variation::UNAVAILABLE);
            throw UnProcessableResponseException::make('sku-not-found-trendyol');
        }


        $price = $result['price']['value'];
        $rialPrice = Currency::convertToRial($price) * $this->getProfitRatioForVariation($variation);
        $stock = !empty($result['inStock']) ? 88 : 0;

        if (empty($price)) {
            $stock = 0;
        }


        $data = [
            'stock' => $stock,
            'status' => Variation::AVAILABLE,
            'item_type' => Product::VARIATION_UPDATE
        ];


        $this->updateVariationAndLog($variation, $data);

        $updateData = ZitaziUpdateDTO::createFromArray([
            'price' => $rialPrice,
            'stock_quantity' => $stock,
        ]);

        $this->syncZitazi($variation, $updateData);
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
