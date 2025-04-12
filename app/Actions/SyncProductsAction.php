<?php

namespace App\Actions;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCompare;
use App\Models\SyncLog;
use App\Services\WoocommerceService;
use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class SyncProductsAction
{
    private mixed $rate;

    private Client $woocommerce;

    private SyncVariationsActions $syncVariationAction;
    private array $torobHeaders;

    public function __construct(
        public SendHttpRequestAction $sendHttpRequestAction
    ) {
        $this->rate = Currency::syncTryRate();
        $this->woocommerce = WoocommerceService::getClient();
        $this->syncVariationAction = app(SyncVariationsActions::class);

        $this->torobHeaders = [
            'Cookie' =>
                'returning_user=true; _ga_RWKMFFVXJX=GS1.1.1744456689.12.1.1744460675.0.0.0; _ga=GA1.1.1811146057.1742477026; search_session=eaqkxqxrtxvbjfkedvseopqxycjkfijj; is_torob_user_logged_in=True; display_mode=; _ga_DG18N985FG=GS1.1.1744457392.1.1.1744457452.0.0.0; _gid=GA1.2.2113594680.1744457393; _ga_S1W5P3WLLJ=GS1.1.1744457415.1.1.1744457477.0.0.0; csrftoken=Umzus7ARY9anfSp4e0QHSebSNLtmXZhy; user_access_dict="eyJ1c2VyX3R5cGUiOiAic2hvcF9zdGFmZiIsICJpbnN0YW5jZXMiOiB7fX0="; trb_clearance=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDQ0NjI0OTcsIm5iZiI6MTc0NDQ2MDY5Nywic3ViIjoiY2UzNzE4NDE1YzRkODNlNzE3YjVlNzk2ZTgwZjE3MDczNTBlYTVhZmJjMmNmMmFiOTEzZWFjNDY4MTMwMmFmOCJ9.2DUPDuh8b8Uq4IeXyauF2HcAEy7fXuo18PBnghHjSA4',
            'user-agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:129.0) Gecko/20100101 Firefox/129.0',
        ];
    }

    public function __invoke(Product $product): void
    {
        if ($product->belongsToTrendyol()) {
            $this->syncTrendyol($product);
        }
        if ($product->belongsToElele()) {
            $this->syncElele($product);
        }
        if ($product->belongsToIran()) {
            $this->syncIran($product);
        }
    }

    public function syncTrendyol(Product $product): void
    {
        $response = ($this->sendHttpRequestAction)('get', $product->trendyol_source)->body();
        $crawler = new Crawler($response);

        $price = null;
        $stock = 0;
        $rialPrice = null;

        foreach (range(2, 5) as $i) {
            $priceElement = $crawler->filter("body > script:nth-child($i)")->first();
            if ($priceElement->count() > 0) {
                $pattern = '/"discountedPrice"\s*:\s*\{.*?\}/';
                $price = preg_match($pattern, $priceElement->text(), $matches);
                if ($matches) {
                    $json = json_decode('{'.$matches[0].'}', true);
                    $price = $json['discountedPrice']['value'];
                    $price = (int) str_replace(',', '.', trim($price));
                    $rialPrice = $this->rate * $price;
                    $rialPrice = $rialPrice * $this->getProfitRatioForProduct($product);
                    $rialPrice = floor($rialPrice / 10000) * 10000;
                    break;
                }
            }
        }

        $stock = $crawler->filter('div.product-button-container .buy-now-button-text')->first();
        if ($stock->count() > 0) {
            $stock = 88;
        } else {
            $stock = 0;
        }

        if (
            $stock == 0
            && $product->belongsToDecalthon()
            && $product->decathlonVariation()->exists()
        ) {
            [
                $price,
                $stock,
                $rialPrice,
            ] = ($this->syncVariationAction)->getVariationData($product->decathlonVariation);
        }

        if (empty($price)) {
            $stock = 0;
            $price = null;
        }

        $data = [
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice,
        ];

        $this->updateAndLogProduct($product, $data);

        if (! $product->belongsToIran()) {
            $this->syncSource($product);
        }

    }

    private function syncSource(Product $product): void
    {
        $stock = 'outofstock';

        if (! empty($product->stock) && $product->stock > 0) {
            $stock = 'instock';
        }

        $data = [
            'price' => ''.$product->rial_price,
            'sale_price' => null,
            'regular_price' => ''.$product->rial_price,
            'stock_quantity' => $product->stock,
            'stock_status' => $stock,
        ];

        $this->updateZitazi($product, $data);
    }

    public function syncIran(Product $product): void
    {
        $url = null;
        if ($product->digikala_source) {
            $url = "https://api.digikala.com/v2/product/$product->digikala_source/";
        }

        $digiPrice = null;
        $torobMinPrice = null;
        $zitaziTorobPrice = null;
        $minDigiPrice = null;
        $zitazi_digikala_price_recommend = null;
        $zitazi_torob_price_recommend = null;

        if ($url) {
            try {
                $response = ($this->sendHttpRequestAction)('get', $url)->collect();

                $variants = collect(data_get($response, 'data.product.variants'))
                    ->map(function ($item) {
                        $item['seller_id'] = data_get($item, 'seller.id');

                        return $item;
                    })->keyBy('seller_id');
                $digiPrice = data_get($variants, '69.price.selling_price');
                $minDigiPrice = $variants->filter(function ($i) {
                    return $i['seller_id'] != 69;
                })->pluck('price.selling_price')->min();

                $sellersCount = $variants->pluck('seller_id')->count();

                if (! $digiPrice) {
                    $digiPrice = data_get($response, 'data.product.default_variant.price.selling_price');
                    Log::info('zitazi_not_available', [
                        'url' => $url,
                    ]);
                }

                if ($sellersCount > 1) {
                    if ($product->belongsToTrendyol()) {
                        $product->min_price = $product->price * Currency::syncTryRate() * 1.2;
                        $product->update();
                    }

                    $zitazi_digikala_price_recommend = $minDigiPrice * (99.5 / 100);

                    if (! empty($product->min_price)) {
                        if ($zitazi_digikala_price_recommend < $product->min_price) {
                            $zitazi_digikala_price_recommend = $product->min_price;
                        }
                    }

                    $zitazi_digikala_price_recommend = floor($zitazi_digikala_price_recommend / 10000) * 10000;

                }

                $digiPrice = $digiPrice / 10;
                $minDigiPrice = $minDigiPrice / 10;
                $zitazi_digikala_price_recommend = $zitazi_digikala_price_recommend / 10;
            } catch (\Exception $e) {
                Log::error('error_digi_fetch'.$product->id, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $responseTorob = ($this->sendHttpRequestAction)('get', $product->torob_source, $this->torobHeaders)->body();
            dump($responseTorob);
            $crawler = new Crawler($responseTorob);
            $element = $crawler->filter('script#__NEXT_DATA__')->first();
            if ($element->count() > 0) {
                $data = collect(json_decode($element->text(), true));
                $sellers = data_get($data, 'props.pageProps.baseProduct.products_info.result');

                $zitaziTorobPrice = collect($sellers)->firstWhere('shop_id', '=', 12259)['price'] ?? null;
                $torobMinPrice = collect($sellers)->filter(function ($i) {
                    return data_get($i, 'shop_id') != 12259;
                })->pluck('price')->filter(fn ($p) => $p > 0)->min();

                if (! empty($sellers) && count($sellers) > 1) {
                    if ($product->belongsToTrendyol()) {
                        $product->min_price = $product->price * Currency::syncTryRate() * 1.2;
                        $product->update();
                    }

                    $zitazi_torob_price_recommend = $torobMinPrice * (99.5 / 100);
                    $zitazi_torob_price_recommend = floor($zitazi_torob_price_recommend / 10000) * 10000;

                    if (! empty($product->min_price)) {
                        if ($zitazi_torob_price_recommend < $product->min_price) {
                            $zitazi_torob_price_recommend = $product->min_price;
                        }

                        $zitazi_torob_price_recommend = floor($zitazi_torob_price_recommend / 10000) * 10000;

                        $this->updateProductOnTorob($product, $zitazi_torob_price_recommend);

                    }

                } elseif ($product->isForeign()) {
                    $this->syncSource($product);
                }
            }
        } catch (\Exception $e) {
            Log::error('error_torob_fetch'.$product->id, [
                'error' => $e->getMessage(),
            ]);
        }

        ProductCompare::updateOrCreate(
            [
                'product_id' => $product->id,
            ],
            [
                'zitazi_digi_ratio' => ! empty($minDigiPrice) ? $digiPrice / $minDigiPrice : null,
                'zitazi_torob_ratio' => ! empty($torobMinPrice) ? $zitaziTorobPrice / $torobMinPrice : null,
                'digikala_zitazi_price' => $digiPrice,
                'digikala_min_price' => $minDigiPrice,
                'torob_min_price' => $torobMinPrice,
                'zitazi_torob_price' => $zitaziTorobPrice,
                'zitazi_torob_price_recommend' => $zitazi_torob_price_recommend,
                'zitazi_digikala_price_recommend' => $zitazi_digikala_price_recommend,
            ]
        );

    }

    private function updateProductOnTorob(Product $product, $zitazi_digikala_price_recommend): void
    {
        $data = [
            'price' => ''.$zitazi_digikala_price_recommend,
            'sale_price' => null,
            'regular_price' => ''.$zitazi_digikala_price_recommend,

        ];

        if ($product->isForeign()) {
            $stock = 'outofstock';
            if (! empty($product->stock) && $product->stock > 0) {
                $stock = 'instock';
            }

            $data['stock_quantity'] = $product->stock;
            $data['stock_status'] = $stock;
        }

        $this->updateZitazi($product, $data);
    }

    public function syncElele(Product $product): void
    {
        $response = ($this->sendHttpRequestAction)('get', $product->elele_source)->body();

        $price = null;
        $stock = 0;
        $rialPrice = null;

        $crawler = new Crawler($response);

        foreach (range(2, 5) as $i) {
            $dom = $crawler->filter("#formGlobal > script:nth-child($i)")->first();

            if ($dom->count() > 0) {
                preg_match('/"productPriceKDVIncluded":([0-9]+\.[0-9]+)/', $dom->text(), $matches);

                if (isset($matches[1])) {
                    $price = $matches[1];
                    $rialPrice = $price * $this->getProfitRatioForProduct($product) * $this->rate;
                    $rialPrice = floor($rialPrice / 10000) * 10000;
                    break;

                }
            }

            $stockElement = 'input.Addtobasket.button.btnAddBasketOnDetail';
            $stockResult = $crawler->filter($stockElement)->first();
            $stock = 0;
            if ($stockResult->count() > 0) {
                $stock = 88;
            }
        }

        if (empty($price)) {
            $stock = 0;
            $price = null;
        }

        $data = [
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice,
        ];

        $this->updateAndLogProduct($product, $data);

        if (! $product->belongsToIran()) {
            $this->syncSource($product);
        }

    }

    private function updateZitazi(Product $product, array $data): void
    {
        if ($product->onPromotion()) {
            return;
        }

        Log::info("product_update_data_{$product->id}", [
            'body' => $data,
            'product' => $product->toArray(),
        ]);

        try {
            $response = $this->woocommerce->post("products/{$product->own_id}", $data);

            Log::info(
                "product_update_source_{$product->id}",
                [
                    'price' => data_get($response, 'price'),
                    'sale_price' => data_get($response, 'sale_price'),
                    'regular_price' => data_get($response, 'regular_price'),
                    'stock_quantity' => data_get($response, 'stock_quantity'),
                    'stock_status' => data_get($response, 'stock_status'),
                    'zitazi_id' => data_get($response, 'id'),
                    'product' => $product->toArray(),
                ]
            );
        } catch (HttpClientException $e) {
            $body = $e->getResponse()->getBody();
            $json = json_decode($body, true);

            Log::error('WooCommerce error product', [
                'code' => $json['code'] ?? 'unknown',
                'message' => $json['message'] ?? 'No message',
                'product_id' => $product->id
            ]);
        }
    }

    private function getProfitRatioForProduct(Product $product)
    {
        if (!empty($product->markup)) {
            return 1 + ($product->markup / 100);
        }

        return 1.6;
    }

    private function updateAndLogProduct(Product $product, array $data): void
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
                'product_own_id' => $product->own_id
            ];

            SyncLog::create($data);
        }

        Log::info("product_update_{$product->id}", [
            'before' => $product->getOriginal(),
            'after' => $product->getChanges(),
        ]);
    }

}
