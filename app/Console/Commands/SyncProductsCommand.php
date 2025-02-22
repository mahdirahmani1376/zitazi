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
    protected $signature = 'app:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $rate;
    private $headers;
    private $woocommerce;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // $seeder = new ProductSeeder();

        // $seeder->run();

        $this->headers = [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3'
        ];

        $this->woocommerce = WoocommerceService::getClient();


        $products = Product::all();

        $this->rate = Currency::syncTryRate();

        $bar = $this->output->createProgressBar($products->count());

        foreach ($products as $product)
        {
            try {
                if ($product->belongsToTrendyol()){
                    // $this->syncTrendyol($product);
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


        $response = Http::acceptJson()->withHeaders($this->headers)->get($product->source_id);
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
                    $rialPrice = floor($rialPrice/1000)*1000;
                    $rialPrice = $rialPrice * 1.6;
                    break;
                }
            }
        }

        $stock = $crawler->filter('div.product-button-container .buy-now-button-text')->first();
        if ($stock->count() > 0) {
            $stock = 5;
        } else {
            $stock = 0;
        }


        if (! $price){
            $stock = 0;
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

        $this->syncSource($product);

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

        $digiPriceAverage = null;

        try {
            $response = Http::withHeaders($this->headers)->acceptJson()->get($url)->collect();

            $digiPrice = collect(data_get($response,'data.product.default_variant.price.selling_price'));
    
        } catch (\Exception $e)
        {
            Log::error('error_digi_fetch'.$product->id,[
                'error' => $e->getMessage()
            ]);
        }

        try {
            $responseTorob = Http::withHeaders($this->headers)->acceptJson()->get($product->torob_source)->body();
    
            $torobPrice = null;
            $crawler = new Crawler($responseTorob);
            $element = $crawler->filter("script#__NEXT_DATA__")->first();
            if ($element->count() > 0) {
                $data = collect(json_decode($element->text(),true));
                $torobPrice = data_get($data,'props.pageProps.baseProduct.price');
            }
        } catch (\Exception $e)
        {
            Log::error('error_torob_fetch'.$product->id,[
                'error' => $e->getMessage()
            ]);
        }

        ProductCompare::create([
            'product_id'=> $product->id,
            'digikala_price'=> $digiPrice,
            'torob_price'=> $torobPrice,
        ]);
    }
}
