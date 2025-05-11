<?php

namespace App\Imports;

use App\Models\Variation;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ImportDecathlonVariation implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        try {
            return $this->updateVariationFromRow($row);
        } catch (\Exception $e) {
            Log::error('error-import', [
                'error' => $e->getMessage(),
            ]);
        }

    }

    public function updateVariationFromRow(array $row)
    {
        $result = Variation::where('own_id', $row['شناسه تنوع در وب سرویس'])->first();

        if (!empty($result)) {
            $result->update([
                'own_id' => $row['شناسه تنوع زیتازی']
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
