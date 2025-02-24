<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;


class ProductExport implements FromCollection,WithHeadings,WithMapping
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Product::with('productCompare:digikala_price,torob_price,id')->orderBy('source')->get();
    }

    public function headings(): array
    {
        return [
            'id',
            'zitazi_id',
            'trendyol_url',
            'source',
            'price',
            'stock',
            'rial_price',
            'digikala_dkp',
            'torob_url',
            'torob_price',
            'created_at',
            'updated_at'
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->own_id,
            $product->source_id,
            $product->source->value,
            $product->price,
            $product->stock,
            $product->rial_price,
            $product->digikala_source,
            $product->digikala_price,
            $product->torob_source,
            $product->torob_price,
            $product->updated_at->toDateTimestring(),
         ]; 
     }
}
