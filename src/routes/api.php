<?php

use App\Actions\Crawler\BaseVariationCrawler;
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

