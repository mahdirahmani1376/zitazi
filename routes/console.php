<?php

use App\Models\Product;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\DomCrawler\Crawler;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test', function () {

    // dd(urldecode('https://torob.com/p/fed4cdaf-1292-4efc-9105-ad7a4cb7c8ab/%D8%AA%D9%84%D8%B3%DA%A9%D9%88%D9%BE-%D9%86%D8%AC%D9%88%D9%85%DB%8C-%D9%88-%D8%B7%D8%A8%DB%8C%D8%B9%D8%AA%DA%AF%D8%B1%D8%AF%DB%8C-%D8%B2%DB%8C%D8%AA%D8%A7%D8%B2%DB%8C-master-70-%D8%A8%D8%A7-%D8%A8%D8%B2%D8%B1%DA%AF%D9%86%D9%85%D8%A7%DB%8C%DB%8C-210-%D8%A8%D8%B1%D8%A7%D8%A8%D8%B1-%D8%A7%D8%B1%D8%B3%D8%A7%D9%84-%D9%81%D9%88%D8%B1%DB%8C'));
    $headers = [
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
    ];
    $responseTorob = Http::withHeaders($headers)->acceptJson()
        ->get('https://torob.com/p/fed4cdaf-1292-4efc-9105-ad7a4cb7c8ab/%D8%AA%D9%84%D8%B3%DA%A9%D9%88%D9%BE-%D9%86%D8%AC%D9%88%D9%85%DB%8C-%D9%88-%D8%B7%D8%A8%DB%8C%D8%B9%D8%AA%DA%AF%D8%B1%D8%AF%DB%8C-%D8%B2%DB%8C%D8%AA%D8%A7%D8%B2%DB%8C-master-70-%D8%A8%D8%A7-%D8%A8%D8%B2%D8%B1%DA%AF%D9%86%D9%85%D8%A7%DB%8C%DB%8C-210-%D8%A8%D8%B1%D8%A7%D8%A8%D8%B1-%D8%A7%D8%B1%D8%B3%D8%A7%D9%84-%D9%81%D9%88%D8%B1%DB%8C/')
        ->body();

    $torobPrice = null;
    $crawler = new Crawler($responseTorob);
    $element = $crawler->filter('script#__NEXT_DATA__')->first();
    if ($element->count() > 0) {
        $data = collect(json_decode($element->text(), true));
        $torobPrice = data_get($data, 'props.pageProps.baseProduct.price');
    }

    dd($torobPrice);
});

Artisan::command('test-torob', function () {
    $headers = [
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
    ];
    $responseTorob = Http::withHeaders($headers)->acceptJson()
//        ->get('https://torob.com/p/fed4cdaf-1292-4efc-9105-ad7a4cb7c8ab/%D8%AA%D9%84%D8%B3%DA%A9%D9%88%D9%BE-%D9%86%D8%AC%D9%88%D9%85%DB%8C-%D9%88-%D8%B7%D8%A8%DB%8C%D8%B9%D8%AA%DA%AF%D8%B1%D8%AF%DB%8C-%D8%B2%DB%8C%D8%AA%D8%A7%D8%B2%DB%8C-master-70-%D8%A8%D8%A7-%D8%A8%D8%B2%D8%B1%DA%AF%D9%86%D9%85%D8%A7%DB%8C%DB%8C-210-%D8%A8%D8%B1%D8%A7%D8%A8%D8%B1-%D8%A7%D8%B1%D8%B3%D8%A7%D9%84-%D9%81%D9%88%D8%B1%DB%8C/')
        ->get('https://torob.com/p/ea1b1064-c96b-49b4-b6c7-ec3d2aa67f62/لگو-دوپلو-مدل-کلاسیک-کد-10914/')
        ->body();

    $torobPrice = null;
    $crawler = new Crawler($responseTorob);
    $element = $crawler->filter('script#__NEXT_DATA__')->first();
    if ($element->count() > 0) {
        $data = collect(json_decode($element->text(), true));
        $sellers = data_get($data, 'props.pageProps.baseProduct.products_info.result');
        $zitaziPrice = collect($sellers)->firstWhere('shop_id', '=', 12259)['price'] ?? null;
        $minPrice = collect($sellers)->pluck('price')->filter(fn ($p) => $p > 0)->min();
        dd($zitaziPrice, $minPrice);
    }
});

Artisan::command('test-excel', function () {});

Artisan::command('test-digi', function () {
    //    $response = \Illuminate\Support\Facades\Http::get('https://api.digikala.com/v2/product/18087380/')->collect();
    // $response = \Illuminate\Support\Facades\Http::get('https://api.digikala.com/v2/product/14851833/')->collect();
    $response = \Illuminate\Support\Facades\Http::get('https://api.digikala.com/v2/product/16723546/')->collect();

    $variants = collect(data_get($response, 'data.product.variants'))
        ->map(function ($item) {
            $item['seller_id'] = data_get($item, 'seller.id');

            return $item;
        })->keyBy('seller_id');
    $zitaziPrice = data_get($variants, '69.price.selling_price');
    $minPrice = $variants->pluck('price.selling_price')->min();
    dd($zitaziPrice, $minPrice);
});

Schedule::command('db:seed')->dailyAt('05:00');
Schedule::command('app:sheet-report')->dailyAt('05:30');
Schedule::command('app:sync-products')->dailyAt('06:00');
Schedule::command('app:sync-products')->dailyAt('18:30');

Artisan::command('test-update', function () {
    dump(1);
    Log::info('test-update');
    sleep(5);
});

Artisan::command('test-products', function () {
    $response = WoocommerceService::getClient()->get('products');

    dd(collect($response)->pluck('id'));
});

Artisan::command('testd', function () {
    $product = Product::whereNotNull('decathlon_url')->first();

    $response = Http::acceptJson()
    // ->get('https://www.decathlon.com.tr/p/erkek-su-gecirmez-outdoor-kar-montu-kislik-mont-kahverengi-sh500-10-degc/_/R-p-331992?mc=8641932')
        ->get($product->url)
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
            $stock = $offer['availability'] == 'https://schema.org/InStock' ? 1 : 0;
            $variations[] = [
                'product_id' => $productId,
                'sku' => $offer['sku'] ?? null,
                'price' => $offer['price'] ?? null,
                'url' => $offer['url'] ?? null,
                'stock' => $stock,
            ];
        }
    }

    // $pattern = '/^__DKT\s*=\s*(.*);$/';
    // $pattern = '/^__DKT\s*=\s*(\{.*\})$/';
    foreach ($variations as &$variation) {
        $skuId = $variation['sku'];
        $pattern = '/"skuId"\s*:\s*"'.preg_quote($skuId, '/').'"\s*,\s*"size"\s*:\s*"([^"]+)"/';

        if (preg_match($pattern, $jsonString, $matches)) {
            $size = $matches[1];
            $variation['size'] = $size;
        }
    }

    foreach ($variations as $variation) {
        Variation::create([
            // 'product_id' => $variation['product_id'],
            'product_id' => $product->id,
            'sku' => $variation['sku'],
            'price' => $variation['price'],
            'url' => $variation['url'],
            'stock' => $variation['stock'],
            'size' => $variation['size'],
        ]);
    }

});
