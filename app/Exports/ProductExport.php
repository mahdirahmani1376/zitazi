<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductExport implements FromCollection,WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Product::with('productCompare:digikala_price,torob_price,id')->get();
    }

    public function headings(): array
    {
        return [
            'id',
            'own_id',
            'source_id',
            'source',
            'price',
            'stock',
            'rial_price',
            'digikala_source',
            'torob_source',
        ];
    }
}
