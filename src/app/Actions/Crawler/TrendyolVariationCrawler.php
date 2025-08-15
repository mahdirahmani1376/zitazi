<?php

namespace App\Actions\Crawler;

use App\Actions\HttpService;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Currency;
use App\Models\Variation;

class TrendyolVariationCrawler extends BaseVariationCrawler
{
    public function crawl(Variation $variation)
    {
        $response = HttpService::getTrendyolData($variation->product->getTrendyolContentId(), $variation->product->getTrendyolMerchantId());
        $data = collect($response['result']['variants'])->keyBy('itemNumber');
        if (isset($variation->item_number)) {
            $result = $data->get($variation->item_number);
        } else {
            $result = collect($response['result']['variants']);
        }

        $price = $result['price']['value'];
        $rialPrice = Currency::convertToRial($price) * $this->getProfitRatioForVariation($variation);
        $stock = !empty($result['inStock']) ? 88 : 0;

        if (empty($price)) {
            $stock = 0;
        }


        $data = [
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice
        ];


        $this->updateVariationAndLog($variation, $data);

        $updateData = ZitaziUpdateDTO::createFromArray([
            'price' => $rialPrice,
            'stock_quantity' => $stock,
        ]);

        $this->syncZitazi($variation, $updateData);
    }

}
