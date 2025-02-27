<?php

use App\Models\Product;
use App\Exports\ProductExport;
use App\Models\ProductCompare;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Route;
use App\Actions\ProductCompareAction;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/report',function (){
   $digikala = \App\Models\Report::query()->where('source','digikala')->orderByDesc('created_at')->first();
   $torob = \App\Models\Report::query()->where(['source' => 'torob'])->orderByDesc('created_at')->first();

   $data = [
       'digikala' => $digikala,
       'torob' => $torob
   ];
   return view('report',[
       'data' => $data
   ]);
});

Route::get('/compare',ProductCompareAction::class);

Route::get('/product-download',function () {
    $now = now()->toDateTimeString();
    return Excel::download(new ProductExport, "products_{$now}.xlsx");
});

Route::get('products',function () {
    return view('product');
});
