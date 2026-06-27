<?php

use App\Actions\Crawler\BaseVariationCrawler;
use App\Actions\UpdateDecathlonVariationAction;
use App\Actions\UpdateEthVariationAction;
use App\Actions\UpdateTrendyolVariationAction;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('amazon-list', function () {
//    return \Illuminate\Support\Facades\Response::json([
//        'data' => [
//            'https://www.amazon.ae/dp/B09NLFPD4Q',
//            'https://www.amazon.ae/dp/B09HL1JM1N'
//        ]
//    ]);
    $results = [];

    $amazonVariations = Variation::where([
        'source' => Product::SOURCE_AMAZON,
    ])->get();

    foreach ($amazonVariations as $variation) {
        $results[] = [
            'id' => $variation->id,
            'url' => "https://www.amazon.ae/dp/{$variation->url}"
        ];
    }

    return Response::json([
        'data' => $results
    ]);

});

Route::put('/variations/{variation}/update', function (
    Variation            $variation,
    Request              $request,
    BaseVariationCrawler $baseVariationCrawler
) {

    $rialPrice = \App\Models\Currency::convertDirhamToRial($request->get('price'));

    $data = [
        'price' => $request->get('price'),
        'stock' => $request->get('stock'),
        'rial_price' => $rialPrice,
    ];

    $baseVariationCrawler->updateVariationAndLog($variation, $data);

    $dto = ZitaziUpdateDTO::createFromArray([
        'price' => $rialPrice,
        'stock_quantity' => $request->get('stock'),
    ]);

    $baseVariationCrawler->syncZitazi($variation, $dto);

    return \Illuminate\Support\Facades\Response::json(
        [
            'status' => 'success'
        ]
    );
});

Route::get('decathlon-list', function () {
    return Response::json([
        'data' => Product::query()
            ->whereNotNull('decathlon_url')
            ->orderBy('updated_at', 'asc')
            ->paginate()
    ]);
});

Route::get('trendyol-list', function () {

    $data = Product::query()
        ->whereNotNull('trendyol_source')
        ->orderBy('updated_at', 'asc')
        ->paginate()
        ->through(function ($product) {
            $url = 'https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/product-detail/content';

            $params = http_build_query([
                'contentId' => $product->getTrendyolContentId(),
                'merchantId' => $product->getTrendyolMerchantId(),
            ]);

            $product->full_url = $url . '?' . $params;

            return $product;
        });

    return Response::json([
        'data' => $data
    ]);
});

Route::get('decathlon-list-retry', function () {
    return Response::json([
        'data' => Product::query()
            ->whereNotNull('decathlon_url')
            ->whereDoesntHave('variations')
            ->paginate()
    ]);
});

Route::post('store-decathlon', function (Request $request, UpdateDecathlonVariationAction $action) {
    $action->execute($request->all());
    return response()->json(['status' => 'ok']);
});

Route::post('store-trendyol', function (Request $request, UpdateTrendyolVariationAction $action) {
    $action->execute($request->all());
    return response()->json(['status' => 'ok']);
});

Route::post('store-eth', function (Request $request, UpdateEthVariationAction $action) {
    $action->execute($request->all());
    return response()->json(['status' => 'ok']);
});

Route::post('update-decathlon-product', function (Request $request) {

    $product = Product::firstWhere('own_id', $request->get('decathlon_own_id'))?->toArray();
    if (empty($product)) {
        return back()->withErrors([
            'message' => 'محصولی با شناسه تنوع مورد نظر یافت نشد'
        ]);
    }

    if (!empty($product->decathlon_url)) {
        return back()->withErrors([
            'message' => 'لینک دکتلون خالی است'
        ]);
    }

    $response = \Illuminate\Support\Facades\Http::post('172.18.0.1:3000/scrape', $product);
    if (!$response->successful()) {
        return back()->withErrors([
            'message' => 'خطایی رخ داد'
        ]);
    }
    return back()->with('success', 'آپدیت محصول انجام شد');

})->name('product.update.decathlon');

Route::get('decathlon-list-test', function () {
    return Response::json([
        'data' => Product::query()
            ->whereNotNull('decathlon_url')
            ->orderBy('updated_at', 'asc')
            ->pluck('id')
    ]);
});

Route::get('eth-list', function () {
    return Response::json([
        'data' => Product::query()
            ->whereNotNull('elele_source')
            ->orderBy('updated_at', 'asc')
            ->paginate()
    ]);
});

/**
 * host.docker.internal:80/get-schema-test
 */
Route::match(['post', 'get'], 'get-schema-test', function () {
    $data = json_decode('{
  "carts": [
    {
      "cart_id": "test",
      "products": [
        {
          "product_id": 1,
          "product_name": "product name 1",
          "variations": [
            {
              "variation_id": 1,
              "variation_name": "variation name 1"
            },
            {
              "variation_id": 2,
              "variation_name": "variation name 1"

            }
          ]
        },
        {
          "product_id": 2,
          "product_name": "product name 1",
          "variations": [
            {
              "variation_id": 3,
              "variation_name": "variation name 1"

            },
            {
              "variation_id": 4,
              "variation_name": "variation name 1"

            }
          ]
        }
      ],
      "total_cart": "1+2"
    },
    {
      "cart_id": 2,
      "products": [
        {
          "product_id": 3,
          "product_name": "product name 1",
          "variations": [
            {
              "variation_id": 5,
              "variation_name": "variation name 1"

            },
            {
              "variation_id": 6,
              "variation_name": "variation name 1"

            }
          ]
        },
        {
          "product_id": 4,
          "product_name": "product name 1",
          "variations": [
            {
              "variation_id": 7,
              "variation_name": "variation name 1"

            },
            {
              "variation_id": 8,
              "variation_name": "variation name 1"

            }
          ]
        },
        {
          "product_id": 5,
          "product_name": "product name 1",
          "variations": [
            {
              "variation_id": 9,
              "variation_name": "variation name 1"

            },
            {
              "variation_id": 10,
              "variation_name": "variation name 1"

            }
          ]
        }
      ],
      "total_cart": "3+4"
    }
  ],
  "total_carts": "1+2+3+4"
}', true);

    return response()->json($data, 200);
});

Route::match(['post', 'get'], 'get-1249', function () {
    $data = [
        [
            'key' => 'key1',
            'product' => 'test'
        ],
        [
            'key' => 'key2',
            'product' => 'test2'
        ],
        [
            'key' => 'key3',
            'product' => 'test3'
        ]
    ];

    return response()->json($data, 200);
});

/**
 * host.docker.internal:80/api/itest
 */
Route::match(['post', 'get'], 'itest', function (Request $request) {
    info('recieved-request', ['request', $request->all()]);

    return response()->json([
        'error' => 'test error',
        'test_body' => 'hi'
    ], 403);
//    throw new Exception('test');

    return response()->json([
        'data' => 'hi'
    ]);
});
