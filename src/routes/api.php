<?php

use App\Actions\Crawler\BaseVariationCrawler;
use App\Actions\UpdateDecathlonVariationAction;
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
            ->whereNot('decathlon_url', '=', '')
            ->orderBy('updated_at', 'asc')
//            ->whereIn('own_id', [
//                844747
//            ])
            ->paginate()
    ]);
});

Route::get('decathlon-list-retry', function () {
    return Response::json([
        'data' => Product::query()
            ->whereNot('decathlon_url', '=', '')
            ->orderBy('updated_at', 'asc')
            ->whereDoesntHave('variations')
            ->paginate()
    ]);
});

Route::post('store-decathlon', function (Request $request, UpdateDecathlonVariationAction $action) {
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

    $response = \Illuminate\Support\Facades\Http::post('172.17.0.1:3000/scrape', $product);
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
            ->whereNot('decathlon_url', '=', '')
            ->orderBy('updated_at', 'asc')
            ->pluck('id')
    ]);
});
