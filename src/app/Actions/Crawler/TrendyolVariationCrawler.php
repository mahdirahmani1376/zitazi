<?php

namespace App\Actions\Crawler;

use App\Actions\HttpService;
use App\Actions\LogManager;
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
            LogManager::logVariation($variation, 'response variation', [
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

        $item_type = count($response['result']['variants']) > 1 ? Product::PRODUCT_UPDATE : Product::VARIATION_UPDATE;

        $data = collect($response['result']['variants'])->keyBy('itemNumber');

        $result = null;
        if (isset($variation->item_number) && $variation->item_type = Product::VARIATION_UPDATE) {
            $result = $data->get($variation->item_number);
        } elseif ($variation->item_type = Product::PRODUCT_UPDATE) {
            $result = collect($response['result']['variants'])[0];
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
            'item_type' => $item_type,
            'rial_price' => $rialPrice,
        ];

        $this->updateVariationAndLog($variation, $data);
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
