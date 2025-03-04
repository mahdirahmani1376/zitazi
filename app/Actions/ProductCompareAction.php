<?php

namespace App\Actions;

use Illuminate\Http\Request;
use App\Models\ProductCompare;
use Illuminate\Database\Eloquent\Builder;

class ProductCompareAction
{
    public function __invoke()
    {
    $productCompares = ProductCompare::query()
        ->has('product')
        ->with('product')
        ->get()
        ->each(function (ProductCompare $productCompare){
            $digiClass = $this->getDigiCellColor($productCompare);

            $torobClass = $this->getTorobCellColor($productCompare);

            $digiWarning = $productCompare->zitazi_digikala_price_recommend ? 'orange' : '';
            $torobWarning = $productCompare->zitazi_torob_price_recommend ? 'orange' : '';

            $productCompare->setAttribute('price_digi',$productCompare->digikala_zitazi_price);
            $productCompare->setAttribute('price_torob',$productCompare->zitazi_torob_price);
            $productCompare->setAttribute('digi_class',$digiClass);
            $productCompare->setAttribute('torob_class',$torobClass);
            $productCompare->setAttribute('digi_recommend',$digiWarning);
            $productCompare->setAttribute('torob_recommend',$torobWarning);
        });
        return view('product-compare',[
            'data' => $productCompares
        ]);
    }

    private function getDigiCellColor($productCompare)
    {
        if (! $productCompare->digikala_zitazi_price)
        {
            return 'yellow';
        }
        if ($productCompare->digikala_zitazi_price == $productCompare->digikala_min_price)
        {
            return 'green';
        }
        if ($productCompare->digikala_zitazi_price < $productCompare->digikala_min_price * 1.1)
        {
            return 'orange';
        }
        if ($productCompare->digikala_zitazi_price > $productCompare->product->rial_price * 1.1)
        {
            return 'red';
        }
    }

    private function getTorobCellColor($productCompare)
    {
        if (! $productCompare->zitazi_torob_price)
        {
            return 'yellow';
        }
        if ($productCompare->zitazi_torob_price == $productCompare->torob_min_price)
        {
            return 'green';
        }
        if ($productCompare->zitazi_torob_price < $productCompare->torob_min_price * 1.1)
        {
            return 'orange';
        }
        if ($productCompare->zitazi_torob_price < $productCompare->torob_min_price * 1.1)
        {
            return 'red';
        }
    }
}
