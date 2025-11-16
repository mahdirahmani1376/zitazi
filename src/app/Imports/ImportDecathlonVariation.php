<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Variation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ImportDecathlonVariation implements ToModel, WithHeadingRow, WithChunkReading, ShouldQueue
{
    public function chunkSize(): int
    {
        return 100;
    }
    public function model(array $row)
    {
        try {
            return $this->updateVariationFromRow($row);
        } catch (\Exception $e) {
            Log::error('error-import', [
                'error' => $e->getMessage(),
                'row' => $row,
            ]);
        }

    }

    public function updateVariationFromRow(array $row)
    {
        $result = Variation::where('id', $row['شناسه تنوع در وب سرویس'])->first();
        $itemType = Product::VARIATION_UPDATE;
        if (empty($row['شناسه تنوع زیتازی']) && empty($result->own_id)) {
            $itemType = Product::PRODUCT_UPDATE;
        }

        if (!empty($result)) {

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

}
