<?php

namespace App\Actions;

use App\Models\Currency;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Automattic\WooCommerce\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class SyncVariationsActions
{
    private Client $woocommerce;

    private mixed $rate;

    public function __construct()
    {
        $this->rate = Currency::syncTryRate();
        $this->woocommerce = WoocommerceService::getClient();
    }

    public function __invoke(Variation $variation,bool $sync = true): void
    {
        $this->updateVariation($variation);

    }

    public function updateVariation(Variation $variation): void
    {
        $data = $this->getVariationData($variation);

        $variation->update($data);

        Log::info("variation_update_{$variation->id}", [
            'before' => $variation->getOriginal(),
            'after' => $variation->getChanges(),
            'data' => $data,
        ]);

        $this->syncZitazi($variation);

    }

    private function syncZitazi(Variation $variation)
    {
        $data = [
            'price' => ''.$variation->rial_price,
            'sale_price' => null,
            'regular_price' => ''.$variation->rial_price,
            'stock_quantity' => $variation->stock,
            'stock_status' => $variation->stock > 0 ? 'instock' : 'outofstock',
        ];

        $response = $this->woocommerce->post("products/{$variation->product->own_id}/variations/{$variation->own_id}", $data);
        Log::info(
            "product_update_source_{$variation->id}",
            [
                'price' => data_get($response, 'price'),
                'sale_price' => data_get($response, 'sale_price'),
                'regular_price' => data_get($response, 'regular_price'),
                'stock_quantity' => data_get($response, 'stock_quantity'),
                'stock_status' => data_get($response, 'stock_status'),
            ]
        );
    }

    public function getVariationData(Variation $variation)
    {
        $response = Http::withHeaders([
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
        ])->get($variation->url)->body();

        $element = 'script[type="application/ld+json"]';

        $crawler = new Crawler($response);
        $element = $crawler->filter($element)->first();

        $variations = [];
        if ($element->count() > 0) {

            $data = collect(json_decode($element->text(), true));
            $productId = data_get($data, 'productID');
            $offers = collect($data->get('offers'))->collapse();
            foreach ($offers as $offer) {
                $variationStock = $offer['availability'] == 'https://schema.org/InStock' ? 88 : 0;
                $variations[] = [
                    'product_id' => $productId,
                    'sku' => $offer['sku'] ?? null,
                    'price' => $offer['price'] ?? null,
                    'url' => $offer['url'] ?? null,
                    'stock' => $variationStock,
                ];
            }
        }

        $variations = collect($variations)->keyBy('sku');
        $stock = $variations[$variation->sku]['stock'];

        $price = (int) str_replace(',', '.', trim($variation['price']));
        $rialPrice = $this->rate * $price;
        $rialPrice = $rialPrice * 1.6;

        $rialPrice = floor($rialPrice / 10000) * 10000;

        if (empty($price) || empty($rialPrice)) {
            $stock = 0;
            $price = null;
            $rialPrice = null;
        }

        $data = [
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice,
        ];

        return $data;
    }
}
