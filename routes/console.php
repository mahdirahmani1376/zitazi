<?php

use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

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
    $response = Http::get('https://api.digikala.com/v2/product/16723546/')->collect();

    $variants = collect(data_get($response, 'data.product.variants'))
        ->map(function ($item) {
            $item['seller_id'] = data_get($item, 'seller.id');

            return $item;
        })->keyBy('seller_id');
    $zitaziPrice = data_get($variants, '69.price.selling_price');
    $minPrice = $variants->pluck('price.selling_price')->min();
    dd($zitaziPrice, $minPrice);
});
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
Artisan::command('torob_add_id', function () {
    foreach (Product::whereNotNull('torob_source')->get() as $product) {

        $product->update([
            'torob_id' => ! empty($product->torob_source) ? data_get(explode('/', urldecode($product->torob_source)), 4) : null,
        ]);
    }
});
Artisan::command('elele_test', function () {
    //    $url = 'https://www.elelebaby.com/elele-misto-4in1-bebek-mama-sandalyesi-ve-mama-oturagi-ic-pedli-yesil';
    $url = 'https://www.elelebaby.com/elele-lula-travel-sistem-bebek-arabasi-siyah-gri';

    $response = Http::withHeaders(
        [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
        ]
    )->get($url)->body();

    $crawler = new Crawler($response);

    foreach (range(2, 5) as $i) {
        $dom = $crawler->filter("#formGlobal > script:nth-child($i)")->first();

        if ($dom->count() > 0) {
            preg_match('/"productPriceKDVIncluded":([0-9]+\.[0-9]+)/', $dom->text(), $matches);

            if (isset($matches[1])) {
                $price = $matches[1];
                dump($price);
                $price = $price * 1.60 * Currency::syncTryRate();
                $price = (int) ($price) * 10000 / 10000;
                dump($price);
                break;

            }

            dump($i);

        }

        $stockElement = 'input.Addtobasket.button.btnAddBasketOnDetail';
        $stockResult = $crawler->filter($stockElement)->first();
        $stock = 0;
        if ($stockResult->count() > 0) {
            $stock = 88;
        }

        dump($stock);
    }
});
Artisan::command('test', function () {
    dump(Product::first());
});
Artisan::command('digi-cat', function () {
    $url = 'https://api.digikala.com/v1/categories/stroller-and-carrier/search/?has_selling_stock=1&q=کالسکه&sort=7';

    $response = Http::get($url)->json();

    $products = data_get($response, 'data.products');

    $categories = [];
    foreach ($products as $product) {
        $categories[$product['id']]['base_category'] = data_get($product, 'data_layer.category');
        foreach (range(2, 7) as $i) {
            $text = "item_category{$i}";
            if (! empty($category = data_get($product, "data_layer.{$text}"))) {
                $categories[$product['id']]['sub_category'][] = $category;
            }
        }
    }

    dump($categories);

});
Artisan::command('subcat-group', function () {
    $q = \App\Models\SubCategory::query()
        ->selectRaw('name,count(name)')
        ->groupBy('name')
        ->get()
        ->toArray();

    dump($q);
});
Artisan::command('ebek-test', function () {

    $url = 'https://www.e-bebek.com/joie-i-juva-step-travel-sistem-bebek-arabasi-p-joi-t1608dasha001';

    $response = Http::withHeaders(
        [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
        ]
    )->get($url)->body();

    $crawler = new Crawler($response);

    //    foreach (range(41, 45) as $i) {
    //        $dom = $crawler->filter("head > script:nth-child({$i})")->first();
    $dom = $crawler->filter('script[type="application/ld+json"]')->eq(0);
    //        dump($i);

    if ($dom
        //            &&
        //            preg_match('/^\{"@co/', $dom->text(), $matches)
    ) {
        $data = json_decode($dom->text(), true);
        $price = $data['offers']['price'];
        dump($price);
        $price = $price * 1.60 * Currency::syncTryRate();
        $price = (int) ($price) * 10000 / 10000;
        dump($price);

        $stock = $data['offers']['availability'] == 'https://schema.org/InStock' ? 88 : 0;

        dump($stock);

        //            break;

        //        }

    }

});
Artisan::command('toyz_shop', function () {
    $url = 'http://www.toyzzshop.com/monster-high-gizemli-sirlar-havali-pijama-partisi-serisi-surpriz-paket-hyv64?serial=92603';
    $response = Http::withHeaders(
        [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
        ]
    )->get($url)->body();

    $crawler = new Crawler($response);

    $dom = $crawler->filter('script[type="application/ld+json"]')->eq(1);
    if ($dom->count() > 0) {
        $data = json_decode($dom->text(), true);
        $price = $data['offers']['lowPrice'];
        $price = $price * 1.60 * Currency::syncTryRate();
        $price = (int) ($price) * 10000 / 10000;
    }

    preg_match('/"stock"\s*:\s*(\d+)/', $response, $matches);

    if (! empty($matches[1])) {
        $stock = intval($matches[1]);
    }

    dump($price, $stock);

});
Artisan::command('sync-variations', function (\App\Actions\SyncVariationsActions $syncVariationsActions) {
    $syncVariationsActions();
});

Artisan::command('test-job', function () {
    \App\Jobs\TestJob::dispatch();
});

Artisan::command('cancel-batch {batch}', function ($batch) {
    \Illuminate\Container\Container::getInstance()->make(\Illuminate\Bus\BatchRepository::class)?->find($batch)?->cancel();
});

Artisan::command('test', function () {
    \App\Jobs\FailedJob::dispatch();
});

Artisan::command('sync-torob', function () {
    $products = Product::where('torob_source', '!=', '')->get()->map(function (Product $product) {
        return new \App\Jobs\SyncProductJob($product);
    });

    Artisan::call('db:seed --class=ProductSeeder');



    \Illuminate\Support\Facades\Bus::batch($products)->name('sync-torob')->dispatch();
});
