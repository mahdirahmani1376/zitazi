<?php

namespace App\Actions;

use App\Models\ExternalProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ProductCompare;
use Illuminate\Database\Eloquent\Builder;

class Top100Action
{
    public function __invoke()
    {

        // $torobData = Product::query()
        //     ->whereNotNull('products.torob_source')
        //     ->rightJoin('external_products','products.torob_source','=','external_products.source_id')
        //     ->get();

        // $digiData = Product::query()
        //     ->whereNotNull('products.digikala_source')
        //     ->rightJoin('external_products','products.digikala_source','=','external_products.source_id')
        //     ->get();

        // $data = $digiData->merge($torobData);

        // dd($data->toArray());

        $exports = ExternalProduct::all();
        $digiProducts = Product::query()->whereNotNull('digikala_source')->get()->keyBy('digikala_source');
        $torobSource = Product::query()->whereNotNull('torob_source')->get()->keyBy('torob_source');

        $data = $exports->map(function (ExternalProduct $p) use ($digiProducts,$torobSource){
            $digiAvailable = !empty(data_get($digiProducts,$p->source_id));
            $torobAvailable = !empty(data_get($torobSource,$p->source_id));
            $available = $digiAvailable || $torobAvailable;
            $p->setAttribute('available',$available);
            return $p;
        });

        return view('top-100',[
            'data' => $data
        ]);
    }

}
