<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCompare;
use App\Services\WoocommerceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Automattic\WooCommerce\Client;
use Database\Seeders\ProductSeeder;
use Exception;

class SyncProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-products {--not-sync} {--override-id=}';

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
        // ProductCompare::truncate();

        $this->headers = [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3'
        ];

        $this->rate = Currency::syncTryRate();

        $this->woocommerce = WoocommerceService::getClient();


        if (! empty($this->option('override-id')))
        {
            $product = Product::find($this->option('override-id'));
            $this->syncTrendyol($product);
            return 0;
        }

        $products = Product::all();

        $bar = $this->output->createProgressBar($products->count());

        foreach ($products as $product)
        {
            try {
                if ($product->belongsToTrendyol()){
                    $this->syncTrendyol($product);
                }
                if ($product->belongsToIran()){
                    $this->syncIran($product);
                }
            } catch (Exception $e)
            {
                dump($e->getMessage());
                Log::error("product_update_failed_id:{$product->id}",[
                    'error' => $e->getMessage(),
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
    }

    private function syncTrendyol(Product $product): Product
    {
        $response = Http::acceptJson()->withHeaders($this->headers)->get($product->trendyol_source);
        $crawler = new Crawler($response);

        $price = null;
        $stock = 0;
        $rialPrice = null;

        foreach (range(2,5) as $i)
        {
            $priceElement = $crawler->filter("body > script:nth-child($i)")->first();
            if ($priceElement->count() > 0) {
                $pattern = '/"discountedPrice"\s*:\s*\{.*?\}/';
                $price = preg_match($pattern,$priceElement->text(),$matches);
                if ($matches)
                {
                    $json = json_decode('{'.$matches[0].'}',true);
                    $price = $json['discountedPrice']['value'];
                    $price = (int) str_replace(',', '.', trim($price));
                    $rialPrice = $this->rate * $price;

                    if ($product->belongsToIran())
                    {
                        $rialPrice = $rialPrice * 1.2;
                    } else {
                        $rialPrice = $rialPrice * 1.6;
                    }

                    $rialPrice = floor($rialPrice/10000)*10000;
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


        if (empty($price)){
            $stock = 0;
            $price = null;
        }

        $product->update([
            'price' => $price,
            'stock' => $stock,
            'rial_price' => $rialPrice
        ]);


        Log::info("product_update_{$product->id}",[
            'before' => $product->getOriginal(),
            'after' => $product->getChanges()
        ]);

        if (! $this->option('not-sync'))
        {
           $this->syncSource($product);
        }

        return $product;
    }

    private function syncSource(Product $product)
    {
        $data = [
            'regular_price' => ''.$product->rial_price,
            "stock_quantity" => $product->stock,
            "stock_status" => $product->stock > 0 ? 'instock' : 'outofstock',
        ];

        $response = $this->woocommerce->post("products/{$product->own_id}",$data);
        Log::info(
            "product_update_source_{$product->own_id}",
            (array) $response
        );

        return $response;
    }

    private function syncIran(Product $product)
    {
        $url = "https://api.digikala.com/v2/product/$product->digikala_source/";

        $digiPrice = null;
        $torobMinPrice = null;
        $zitaziTorobPrice = null;
        $minDigiPrice = null;
        $zitazi_digikala_price_recommend=null;
        $zitazi_torob_price_recommend=null;

        try {
            $response = Http::withHeaders($this->headers)->acceptJson()->get($url)->collect();

            $variants = collect(data_get($response,'data.product.variants'))
            ->map(function($item){
                $item['seller_id'] = data_get($item,'seller.id');
                return $item;
            })->keyBy('seller_id');
            $digiPrice = data_get($variants,'69.price.selling_price');
            $minDigiPrice = $variants->pluck('price.selling_price')->min();

            if (! $digiPrice)
            {
                $digiPrice = data_get($response,'data.product.default_variant.price.selling_price');
                Log::info('zitazi_not_available',[
                    'url' => $url
                ]);
            }
            if ($digiPrice > $minDigiPrice)
            {
                if ($product->belongsToTrendyol())
                {
                    $zitazi_digikala_price_recommend = $product->price * Currency::syncTryRate() * 1.2;
                } else 
                {
                    $zitazi_digikala_price_recommend = $minDigiPrice * (99.5 / 100);
                    if (! empty($product->min_price))
                    {
                        if ($zitazi_digikala_price_recommend < $product->min_price)
                        {
                            $zitazi_digikala_price_recommend = $product->min_price;
                        }
                    }
                }

                $zitazi_digikala_price_recommend = floor($zitazi_digikala_price_recommend/10000)*10000;;

            }

            $digiPrice = $digiPrice / 10;
            $minDigiPrice = $minDigiPrice / 10;
            $zitazi_digikala_price_recommend = $zitazi_digikala_price_recommend / 10;
        } catch (\Exception $e)
        {
            Log::error('error_digi_fetch'.$product->id,[
                'error' => $e->getMessage()
            ]);
        }

        try {
            $responseTorob = Http::withHeaders($this->headers)->acceptJson()->get($product->torob_source)->body();

            $crawler = new Crawler($responseTorob);
            $element = $crawler->filter("script#__NEXT_DATA__")->first();
            if ($element->count() > 0) {
                $data = collect(json_decode($element->text(),true));
                $sellers= data_get($data,'props.pageProps.baseProduct.products_info.result');
                $zitaziTorobPrice = collect($sellers)->firstWhere('shop_id','=',12259)['price'] ?? null;
                $torobMinPrice = collect($sellers)->pluck('price')->filter(fn($p) => $p > 0)->min();
                if ($zitaziTorobPrice > $torobMinPrice)
                {
                    if ($product->belongsToTrendyol())
                    {
                        $zitazi_torob_price_recommend = $product->price * Currency::syncTryRate() * 1.2;
                        $zitazi_torob_price_recommend = floor($zitazi_torob_price_recommend/10000)*10000;

                    } else 
                    {
                        $zitazi_torob_price_recommend = $torobMinPrice * (99.5 / 100);
                        if (! empty($product->min_price))
                        {
                            if ($zitazi_torob_price_recommend < $product->min_price)
                            {
                                $zitazi_torob_price_recommend = $product->min_price;
                            }

                            $zitazi_torob_price_recommend = floor($zitazi_torob_price_recommend/10000)*10000;
                            $this->updateProductOnTorob($product,$zitazi_digikala_price_recommend);
                        }
 
                    }

                }
            }
        } catch (\Exception $e)
        {
            Log::error('error_torob_fetch'.$product->id,[
                'error' => $e->getMessage()
            ]);
        }

        ProductCompare::updateOrCreate(
            [
                'product_id'=> $product->id,
            ]
            ,
            [
                'zitazi_digi_ratio' => !empty($minDigiPrice) ? $digiPrice / $minDigiPrice : null,
                'zitazi_torob_ratio' => !empty($torobMinPrice) ? $zitaziTorobPrice / $torobMinPrice : null,
                'digikala_zitazi_price'=> $digiPrice,
                'digikala_min_price'=> $minDigiPrice,
                'torob_min_price'=> $torobMinPrice,
                'zitazi_torob_price' => $zitaziTorobPrice,
                'zitazi_torob_price_recommend' => $zitazi_torob_price_recommend,
                'zitazi_digikala_price_recommend' => $zitazi_digikala_price_recommend
            ]
            );

    }

    private function updateProductOnTorob(Product $product,$zitazi_digikala_price_recommend)
    {
        $data = [
            'regular_price' => ''.$zitazi_digikala_price_recommend,
            "stock_quantity" => $product->stock,
            "stock_status" => $product->stock > 0 ? 'instock' : 'outofstock',
        ];

        $response = $this->woocommerce->post("products/{$product->own_id}",$data);
        Log::info(
            "product_update_source_{$product->own_id}",
            (array) $response
        );

        return $response;
    }
}
