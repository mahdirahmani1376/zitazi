<?php

namespace App\Actions\Crawler;

use App\Actions\TrendyolParser;
use App\DTO\ZitaziUpdateDTO;
use App\Exceptions\UnProcessableResponseException;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;

class TrendyolVariationCrawler extends BaseVariationCrawler
{
    public function crawl(Variation $variation)
    {
        if ($variation->item_type === Product::VARIATION_UPDATE) {
            [$price, $stock] = $this->getVariationTypeVariationData($variation);
        } else {
            [$price, $stock] = $this->getVariationTypeProductData($variation);
        }

        $rialPrice = Currency::convertToRial($price) * $this->getProfitRatioForVariation($variation);

        if (empty($price)) {
            $stock = 0;
        }

        $data = [
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice
        ];

        dump($data,$variation->id);

        $this->updateVariationAndLog($variation, $data);

        $updateData = ZitaziUpdateDTO::createFromArray([
            'price' => $rialPrice,
            'stock_quantity' => $stock,
        ]);

        $this->syncZitazi($variation, $updateData);
    }

    private function getVariationTypeVariationData(Variation $variation)
    {
        $response = $this->sendHttpRequestAction->sendWithCache('get', $variation->product->trendyol_source);

        $data = app(TrendyolParser::class)->parseVariationTypeVariationResponse($response);

        $itemNumberData = collect($data['data'])->keyBy('item_number');

        if (empty($variation->item_number)) {
            throw UnProcessableResponseException::make("no item number for variation:{$variation->id}");
        }

        $price = data_get($itemNumberData, $variation->item_number . '.price', $data['data'][0]['price']);
        $stock = data_get($itemNumberData, $variation->item_number . '.stock', $data['data'][0]['stock']);

        return [$price, $stock];
    }

    private function getVariationTypeProductData(Variation $variation)
    {
        $response = $this->sendHttpRequestAction->sendWithCache('get', $variation->product->trendyol_source);

        return app(TrendyolParser::class)->parseVariationTypeProductResponse($response);
    }


}
