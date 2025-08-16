<?php

namespace App\Actions\Crawler;

use App\Actions\HttpService;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Currency;
use App\Models\Product;
use App\Models\SyncLog;
use App\Services\WoocommerceService;
use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;
use Exception;
use Illuminate\Support\Facades\Log;

class BaseCrawler
{
    protected mixed $rate;
    private Client $woocommerce;
    protected HttpService $httpService;

    public function __construct()
    {
        $this->httpService = app(HttpService::class);
        $this->rate = Currency::syncTryRate();
        $this->woocommerce = WoocommerceService::getClient();
    }

    protected function syncProductWithZitazi(Product $product, ZitaziUpdateDTO $dto): void
    {
        if ($product->onPromotion() or env('APP_ENV') === 'local') {
            return;
        }

        $stockStatus = ZitaziUpdateDTO::OUT_OF_STOCK;
        if (!empty($dto->stock_quantity > 0)) {
            $stockStatus = ZitaziUpdateDTO::IN_STOCK;
        }

        $data = $dto->getUpdateBody();

        $data['stock_status'] = $stockStatus;

        if (!empty($dto->price)) {
            $data['sale_price'] = null;
            $data['regular_price'] = '' . $dto->price;
        }

        try {
            $this->sendZitaziUpdateRequest($product, $data);
        } catch (HttpClientException $e) {
            $this->handleHttpClientException($e, $product);
        } catch (Exception $e) {
            $this->handleGeneralException($e);
        }
    }

    private function sendZitaziUpdateRequest(Product $product, array $data): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $response = $this->woocommerce->post("products/{$product->own_id}", $data);

        Log::info(
            "product_update_{$product->id}",
            [
                'body' => $data,
                'product' => $product->toArray(),
                'response' => [
                    'price' => data_get($response, 'price'),
                    'sale_price' => data_get($response, 'sale_price'),
                    'regular_price' => data_get($response, 'regular_price'),
                    'stock_quantity' => data_get($response, 'stock_quantity'),
                    'stock_status' => data_get($response, 'stock_status'),
                    'zitazi_id' => data_get($response, 'id'),
                    'product' => $product->toArray(),
                ],
            ]
        );
    }

    protected function updateAndLogProduct(Product $product, array $data): void
    {
        $oldStock = $product->stock;
        $oldPrice = $product->rial_price;

        $product->update($data);

        if ($oldStock != $product->stock || $oldPrice != $product->rial_price) {
            $data = [
                'old_stock' => $oldStock,
                'new_stock' => $product->stock,
                'old_price' => $oldPrice,
                'new_price' => $product->rial_price,
                'product_own_id' => $product->own_id,
            ];

            SyncLog::create($data);
        }
    }

    private function handleHttpClientException(HttpClientException $e, Product $product): void
    {
        $body = $e->getResponse()->getBody();
        $json = json_decode($body, true);

        Log::error('WooCommerce error product', [
            'code' => $json['code'] ?? 'unknown',
            'message' => $json['message'] ?? 'No message',
            'product_id' => $product->id,
        ]);
    }

    private function handleGeneralException(Exception $e): void
    {
        Log::error('General update erro', [
            'error' => $e->getMessage(),
        ]);
    }
}
