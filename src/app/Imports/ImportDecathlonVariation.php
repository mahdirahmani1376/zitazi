<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Variation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ImportDecathlonVariation implements ToModel, WithHeadingRow, ShouldQueue
{
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
            $result->update([
                'own_id' => $row['شناسه تنوع زیتازی'],
                'item_type' => $itemType
            ]);

            Log::info('product-import-update', [
                'before' => $result->getOriginal(),
                'after' => $result->getChanges(),
                'data' => $row,
            ]);

            return $result;
        }

    }

}
