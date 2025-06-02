<?php

namespace App\Exports;

use App\Models\SyncLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OutOfStockExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return SyncLog::orderBy('created_at')
            ->where('created_at', '>', now()->subDay())
            ->whereNot('old_stock', '=', 0)
            ->where('new_stock', '=', 0)
            ->limit(15000)
            ->get();
    }

    public function headings(): array
    {
        return [
            'id',
            'old_stock',
            'new_stock',
            'old_price',
            'new_price',
            'product_own_id',
            'variation_own_id',
            'نام محصول',
            'برند',
            'created_at',
            'updated_at',


        ];
    }

    public function map($row): array
    {
        /** @var SyncLog $row */

        $product = $row->product();
        return [
            'id' => $row->id,
            'old_stock' => $row->old_stock,
            'new_stock' => $row->new_stock,
            'old_price' => $row->old_price,
            'new_price' => $row->new_price,
            'product_own_id' => $row->product_own_id,
            'variation_own_id' => $row->variation_own_id,
            'product_name' => $product?->product_name,
            'brand' => $product?->brand,
            'created_at' => $row->created_at->toDateTimeString(),
            'updated_at' => $row->updated_at?->toDateTimestring(),
        ];
    }
}
