<?php

namespace App\Actions;

use App\Models\Currency;
use App\Models\SyncLog;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class SyncVariationsActions
{
    private Client $woocommerce;

    private mixed $rate;

    public function __construct(
        public SendHttpRequestAction $sendHttpRequestAction
    ) {
        $this->rate = Currency::syncTryRate();
        $this->woocommerce = WoocommerceService::getClient();
    }

    public function __invoke(Variation $variation, bool $sync = true): void
    {
        $this->updateVariation($variation);

    }

    public function updateVariation(Variation $variation): void
    {
        $data = $this->getVariationData($variation);

        $this->logUpdateForVariation($variation, $data);

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

        try {
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
        } catch (HttpClientException $e) {
            $body = $e->getResponse()->getBody();
            $json = json_decode($body, true);

            Log::error('WooCommerce error variation', [
                'code' => $json['code'] ?? 'unknown',
                'message' => $json['message'] ?? 'No message',
                'variation_id' => $variation->id
            ]);
        } catch (\Exception $e) {
            Log::error('error-sync-variation', [
                'error' => $e->getMessage(),
                'variation_id' => $variation->id
            ]);
        }

    }

    public function getVariationData(Variation $variation)
    {
        $response = ($this->sendHttpRequestAction)('get', $variation->url)->body();

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
        if (!(isset($variations[$variation['sku']]))) {
            Log::error('sync-variations-action-sku-not-found', [
                'sku' => $variation['sku'],
                'variation_url' => $variation->url
            ]);
            return [
                'price' => null,
                'stock' => 0,
                'rial_price' => null,
            ];
        }

        $stock = data_get($variations, "{$variation->sku}.stock", 0);

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

    private function logUpdateForVariation(Variation $variation, $data): void
    {
        $oldStock = $variation->stock;
        $oldPrice = $variation->rial_price;

        $variation->update($data);

        if ($oldStock != $variation->stock || $oldPrice != $variation->rial_price) {
            $data = [
                'old_stock' => $oldStock,
                'new_stock' => $variation->stock,
                'old_price' => $oldPrice,
                'new_price' => $variation->rial_price,
                'variation_own_id' => $variation->own_id,
                'product_own_id' => $variation->product->own_id
            ];

            SyncLog::create($data);
        }

        Log::info("variation_update_{$variation->id}", [
            'before' => $variation->getOriginal(),
            'after' => $variation->getChanges(),
            'data' => $data,
        ]);
    }
}
