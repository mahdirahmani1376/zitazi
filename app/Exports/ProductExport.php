<?php

namespace App\Exports;

use App\Enums\SourceEnum;
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
        return Product::with('productCompare')
            ->where('source',SourceEnum::IRAN->value)
            ->orderBy('source')
            ->get();
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
            'zitazi_price',
            'digikala_dkp',
            'digikala_zitazi_price',
            'digikala_min_price',
            'zitazi_digikala_price_recommend',
            'torob_source',
            'torob_min_price',
            'zitazi_torob_price',
            'zitazi_torob_price_recommend',
            'updated_at',
        ];
    }

    public function map($row): array
    {
        return [
            'id' => $row->id,
            'zitazi_id' =>$row->own_id,
            'trendyol_url' =>$row->source_id,
            'source' =>$row->source->value,
            'price' =>$row->price,
            'stock' =>$row->stock,
            'zitazi_price' =>$row->rial_price,
            'digikala_dkp' =>$row->digikala_source,
            'digikala_zitazi_price' => $row->productCompare?->digikala_zitazi_price,
            'digikala_min_price' => $row->productCompare?->digikala_min_price,
            'zitazi_digikala_price_recommend' => $row->productCompare?->zitazi_digikala_price_recommend,
            'torob_url' =>$row->torob_source,
            'torob_min_price' => $row->productCompare?->torob_min_price,
            'zitazi_torob_price' => $row->productCompare?->zitazi_torob_price,
            'zitazi_torob_price_recommend' => $row->productCompare?->zitazi_torob_price_recommend,
            'updated_at' => $row->updated_at->toDateTimestring()
        ];
     }
}
