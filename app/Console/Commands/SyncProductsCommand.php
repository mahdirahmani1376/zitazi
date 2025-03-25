<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCompare;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Automattic\WooCommerce\Client;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class SyncProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-products {--not-sync} {--override-id=} {--d}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $rate;

    private array $headers;

    private Client $woocommerce;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->headers = [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
        ];

        $this->rate = Currency::syncTryRate();

        $this->woocommerce = WoocommerceService::getClient();

        if (! empty($this->option('override-id'))) {
            $product = Product::find($this->option('override-id'));
            if ($product->belongsToTrendyol()) {
                $this->syncTrendyol($product);
            }
            if ($product->belongsToIran()) {
                $this->syncIran($product);
            }
            if ($product->belongsToDecalthon()) {
                $this->syncDecalthon($product);
            }

            return 0;
        }

        if (! empty($this->option('d'))) {
            $products = Product::whereNotNull('decathlon_url')->get();
            foreach ($products as $product) {
                $this->syncDecalthon($product);
            }

            return 0;
        }

        $products = Product::all();

        $bar = $this->output->createProgressBar($products->count());

        foreach ($products as $product) {
            try {
                if ($product->belongsToTrendyol()) {
                    $this->syncTrendyol($product);
                }
                if ($product->belongsToDecalthon()) {
                    $this->syncDecalthon($product);
                }
                if ($product->belongsToIran()) {
                    $this->syncIran($product);
                }
            } catch (Exception $e) {
                dump($e->getMessage());
                Log::error("product_update_failed_id:{$product->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
    }

    private function syncTrendyol(Product $product): Product
    {
        $response = Http::withHeaders($this->headers)->get($product->trendyol_source);
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
                    $rialPrice = $rialPrice * 1.6;
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

        if ($stock == 0 && $product->belongsToDecalthon()) {
            $product = $this->syncProductFromDecalthon($product);
        }

        if (empty($price)) {
            $stock = 0;
            $price = null;
        }

        $product->update([
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice,
        ]);

        Log::info("product_update_{$product->id}", [
            'before' => $product->getOriginal(),
            'after' => $product->getChanges(),
        ]);

        if (! $this->option('not-sync') && ! $product->belongsToIran()) {
            $this->syncSource($product);
        }

        return $product;
    }

    private function syncSource(Product $product)
    {
        $data = [
            'price' => ''.$product->rial_price,
            'sale_price' => null,
            'regular_price' => ''.$product->rial_price,
            'stock_quantity' => $product->stock,
            'stock_status' => $product->stock > 0 ? 'instock' : 'outofstock',
        ];

        Log::info("product_update_data_{$product->id}", $data);

        $response = $this->woocommerce->post("products/{$product->own_id}", $data);
        Log::info(
            "product_update_source_{$product->id}",
            [
                'price' => data_get($response, 'price'),
                'sale_price' => data_get($response, 'sale_price'),
                'regular_price' => data_get($response, 'regular_price'),
                'stock_quantity' => data_get($response, 'stock_quantity'),
                'stock_status' => data_get($response, 'stock_status'),
                'own_id' => data_get($response, 'id'),
            ]
        );

        return $response;
    }

    private function syncIran(Product $product)
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
                $response = Http::withHeaders($this->headers)->acceptJson()->get($url)->collect();

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
            $responseTorob = Http::withHeaders($this->headers)->acceptJson()->get($product->torob_source)->body();

            $crawler = new Crawler($responseTorob);
            $element = $crawler->filter('script#__NEXT_DATA__')->first();
            if ($element->count() > 0) {

                $data = collect(json_decode($element->text(), true));
                $sellers = data_get($data, 'props.pageProps.baseProduct.products_info.result');

                $zitaziTorobPrice = collect($sellers)->firstWhere('shop_id', '=', 12259)['price'] ?? null;
                $torobMinPrice = collect($sellers)->filter(function ($i) {
                    return data_get($i, 'shop_id') != 12259;
                })->pluck('price')->filter(fn ($p) => $p > 0)->min();

                if (count($sellers) > 1) {
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

                        if (! $this->option('not-sync')) {
                            $this->updateProductOnTorob($product, $zitazi_torob_price_recommend);
                        }

                    }

                } elseif (! $this->option('not-sync') && count($sellers) == 1) {
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

    private function updateProductOnTorob(Product $product, $zitazi_digikala_price_recommend)
    {
        $data = [
            'price' => ''.$zitazi_digikala_price_recommend,
            'sale_price' => null,
            'regular_price' => ''.$zitazi_digikala_price_recommend,
            'stock_quantity' => $product->stock,
            'stock_status' => $product->stock > 0 ? 'instock' : 'outofstock',
        ];

        Log::info("product_update_data_{$product->id}", [
            'body' => $data,
            'product' => $product->toArray(),
        ]);

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

        return $response;
    }

    private function syncDecalthon(Product $product)
    {
        $response = Http::withHeaders($this->headers)
            ->get($product->decathlon_url)
            ->body();

        $element = 'script[type="application/ld+json"]';

        $crawler = new Crawler($response);
        $element = $crawler->filter($element)->first();
        $jsonString = $crawler->filter('#__dkt')->first()->text();

        $variations = [];
        if ($element->count() > 0) {

            $data = collect(json_decode($element->text(), true));
            $productId = data_get($data, 'productID');
            $offers = collect($data->get('offers'))->collapse();
            foreach ($offers as $offer) {
                $stock = $offer['availability'] == 'https://schema.org/InStock' ? 88 : 0;
                $variations[] = [
                    'product_id' => $productId,
                    'sku' => $offer['sku'] ?? null,
                    'price' => $offer['price'] ?? null,
                    'url' => $offer['url'] ?? null,
                    'stock' => $stock ?? 0,
                ];
            }
        }

        foreach ($variations as &$variation) {
            $skuId = $variation['sku'];
            $pattern = '/"skuId"\s*:\s*"'.preg_quote($skuId, '/').'"\s*,\s*"size"\s*:\s*"([^"]+)"/';

            if (preg_match($pattern, $jsonString, $matches)) {
                $size = $matches[1];
                $variation['size'] = $size;
            }
        }

        foreach ($variations as $variation) {
            $price = (int) str_replace(',', '.', trim($variation['price']));
            $rialPrice = $this->rate * $price;
            $rialPrice = $rialPrice * 1.6;

            $rialPrice = floor($rialPrice / 10000) * 10000;

            if (empty($price) || empty($rialPrice)) {
                $stock = 0;
                $price = null;
                $rialPrice = null;
            }

            $createData = [
                'product_id' => $product->id,
                'sku' => $variation['sku'],
                'price' => $variation['price'],
                'url' => $variation['url'],
                'stock' => $variation['stock'],
                'size' => $variation['size'],
                'rial_price' => $rialPrice,
            ];

            $variation = Variation::updateOrCreate([
                'sku' => $variation['sku'],
            ], $createData);

            if (! $this->option('not-sync') && ! $product->belongsToIran()) {
                //    $this->syncSourceDecalthon($variation);
            }
        }

        $product->update([
            'decathlon_id' => $productId,
        ]);

    }

    private function syncSourceDecalthon(Variation $variation)
    {
        $data = [
            'price' => ''.$variation->rial_price,
            'sale_price' => null,
            'regular_price' => ''.$variation->rial_price,
            'stock_quantity' => $variation->stock,
            'stock_status' => $variation->stock > 0 ? 'instock' : 'outofstock',
        ];

        $response = $this->woocommerce->post("products/{$variation->product->own_id}", $data);
        Log::info(
            "product_update_source_{$variation->product->own_id}",
            [
                'price' => data_get($response, 'price'),
                'sale_price' => data_get($response, 'sale_price'),
                'regular_price' => data_get($response, 'regular_price'),
                'stock_quantity' => data_get($response, 'stock_quantity'),
                'stock_status' => data_get($response, 'stock_status'),
            ]
        );

        return $response;
    }

    private function syncProductFromDecalthon(Product $product)
    {
        $response = Http::withHeaders($this->headers)
            ->get($product->decathlon_url)
            ->body();

        $element = 'script[type="application/ld+json"]';

        $crawler = new Crawler($response);
        $element = $crawler->filter($element)->first();

        if ($element->count() > 0) {
            $data = collect(json_decode($element->text(), true));
            $offer = collect($data->get('offers'))->collapse()[0];
            $stock = $offer['availability'] == 'https://schema.org/InStock' ? 88 : 0;
            $price = $offer['price'] ?? null;
            $price = (int) str_replace(',', '.', trim($price));
            $rialPrice = $this->rate * $price;
            $rialPrice = $rialPrice * 1.6;
            $rialPrice = floor($rialPrice / 10000) * 10000;

            $product->price = $price;
            $product->rial_price = $rialPrice;
            $product->stock = $stock ?? 0;

            $product->update();
        }

        return $product;
    }
}
