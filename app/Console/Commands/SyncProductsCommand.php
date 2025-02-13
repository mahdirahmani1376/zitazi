<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Product;
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
    protected $signature = 'app:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $rate;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $products = Product::all();

        $this->rate = Currency::syncTryRate();

        $bar = $this->output->createProgressBar($products->count());

        foreach ($products as $product)
        {
            if ($product->belongsToTrendyol()){
                $this->syncTrendyol($product);
            }
            $bar->advance();
        }

        $bar->finish();
    }

    private function syncTrendyol(Product $product): Product
    {
        $headers = [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3'
        ];

        $response = Http::acceptJson()->withHeaders($headers)->get($product->source_id);
        $crawler = new Crawler($response);
        
        $priceElement = $crawler->filter('div.product-price-container span.prc-dsc')->first();
        if ($priceElement->count() > 0) {
            $price = (int) str_replace(',', '.', trim($priceElement->text()));
            $price = $this->rate * $price;
        } else {
            $price = null;
            $stock = 0;
        }

        $stock = $crawler->filter('div.product-button-container .buy-now-button-text')->first();
        if ($stock->count() > 0) {
            $stock = 5;
        } else {
            $stock = 0;
        }

        $product->update([
            'price' => $price,
            'stock' => $stock
        ]);

        Log::info("product_update_{$product->id}",[
            'before' => $product->getOriginal(),
            'after' => $product->getChanges()
        ]);

        return $product;
    }
}