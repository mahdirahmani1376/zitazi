<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/report',function (){
   $digikala = \App\Models\Report::firstWhere('source','digikala');
   $torob = \App\Models\Report::firstWhere(['source' => 'torob']);

   return compact('digikala','torob');
});
