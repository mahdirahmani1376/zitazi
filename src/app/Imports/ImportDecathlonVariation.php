<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Variation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class ImportDecathlonVariation implements OnEachRow, WithHeadingRow, WithChunkReading, ShouldQueue, SkipsEmptyRows
{
    use Importable;

    public function chunkSize(): int
    {
        return 100;
    }

    public function onRow(Row $row)
    {
        return $this->updateVariationFromRow($row->toArray());
    }

    public function updateVariationFromRow(array $row)
    {
        $result = Variation::where('id', $row['شناسه تنوع در وب سرویس'])->first();
        if (empty($result)) {
            Log::error('product-import-update-not-found',
                [
                    'id' => $row['شناسه تنوع در وب سرویس']
                ]
            );
            return $result;
        }

        $itemType = Product::VARIATION_UPDATE;
        if (empty($row['شناسه تنوع زیتازی']) && empty($result?->own_id)) {
            $itemType = Product::PRODUCT_UPDATE;
        }

        $oldOwnId = $result->own_id;
        $oldIsDeleted = $result->is_deleted;
        $oldItemType = $result->item_type;

        $result->update([
            'own_id' => $row['شناسه تنوع زیتازی'],
            'item_type' => $itemType,
            'is_deleted' => $row['غیرفعال'] ?? false
        ]);
        Log::info('product-import-update',
            [
                'own_id' => $row['شناسه تنوع زیتازی'],
                'old_own_id' => $oldOwnId,
                'item_type' => $itemType,
                'old_item_type' => $oldItemType,
                'is_deleted' => $row['غیرفعال'] ?? false,
                'old_is_deleted' => $oldIsDeleted,
            ]
        );

        return $result;
    }

}
