<?php

use App\Models\Product;
use App\Models\ProductCompare;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/report',function (){
   $digikala = \App\Models\Report::firstWhere('source','digikala');
   $torob = \App\Models\Report::firstWhere(['source' => 'torob']);

   $data = [
       'digikala' => $digikala,
       'torob' => $torob
   ];
   return view('report',[
       'data' => $data
   ]);
});

Route::get('/compare',function (){
    $productCompares = ProductCompare::with('product')
    ->get()
    ->each(function (ProductCompare $productCompare){
        $productPrice = $productCompare->product->rial_price;

        $priceDiffDigi = null;
        $digi10percent = null;
        $digiClass = 'bg-light';
        if ($productCompare->digikala_price)
        {
            $digi10percent = $productPrice < $productCompare->digikala_price * 1.1 && $productPrice > $productCompare->digikala_price * 0.9;
            $digiClass = $digi10percent ? 'bg-success' : 'bg-danger'; 
        }


        $priceDiffTorob = null;
        $torob10percent = null;
        $torobClass = 'bg-light';
        if ($productCompare->torob_price)
        {
            $torob10percent = $productPrice < $productCompare->torob_price * 1.1 && $productPrice > $productCompare->torob_price * 0.9;
            $torobClass = $torob10percent ? 'bg-success' : 'bg-danger'; 
        }


        $productCompare->setAttribute('price_digi',$productCompare->digikala_price);
        $productCompare->setAttribute('price_torob',$productCompare->torob_price);
        $productCompare->setAttribute('digi_class',$digiClass);
        $productCompare->setAttribute('torob_class',$torobClass);
    });
    return view('product-compare',[
        'data' => $productCompares
    ]);
});
