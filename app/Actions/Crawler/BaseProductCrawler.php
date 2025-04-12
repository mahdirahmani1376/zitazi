<?php

namespace App\Actions\Crawler;

use App\Actions\SendHttpRequestAction;
use App\Actions\SyncVariationsActions;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Currency;
use App\Models\Product;
use App\Models\SyncLog;
use App\Services\WoocommerceService;
use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;
use Exception;
use Illuminate\Support\Facades\Log;

class BaseProductCrawler
{
    protected mixed $rate;
    protected SyncVariationsActions $syncVariationAction;
    /** @var ProductAbstractCrawler[] */
    protected array $crawlers;
    private Client $woocommerce;
    protected SendHttpRequestAction $sendHttpRequestAction;

    public function __construct(
        array $crawlers
    )
    {
        $this->crawlers = $crawlers;
        $this->sendHttpRequestAction = app(SendHttpRequestAction::class);
        $this->rate = Currency::syncTryRate();
        $this->woocommerce = WoocommerceService::getClient();
        $this->syncVariationAction = app(SyncVariationsActions::class);

    }
    public function crawl(Product $product)
    {
        foreach ($this->crawlers as $crawler) {
            if ($crawler->supports($product)) {
                $crawler->crawl($product);
            }
        }
    }

    protected function getProfitRatioForProduct(Product $product): float|int
    {
        if (!empty($product->markup)) {
            return 1 + ($product->markup / 100);
        }

        return 1.6;
    }

    protected function syncProductWithZitazi(Product $product, ZitaziUpdateDTO $dto): void
    {
        if ($product->onPromotion()) {
            return;
        }

        $stockStatus = 'outOfStock';
        if ($dto->stock_quantity || $dto->price > 0) {
            $stockStatus = 'inStock';
        }

        $data = $dto->getUpdateBody();

        $data['stock_status'] = $stockStatus;
        $data['sale_price'] = null;

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

        Log::info("product_update_{$product->id}", [
            'before' => $product->getOriginal(),
            'after' => $product->getChanges(),
        ]);
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
