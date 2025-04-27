<?php

namespace App\Imports;

use App\Models\Variation;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Session;

class ImportDecathlonVariation implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (!$this->validate($row)) {
            return;
        }

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
        $result = Variation::updateOrCreate(
            ['sku' => $row['شناسه تنوع دکلتون']],
            [
                'own_id' => $row['شناسه تنوع زیتازی'],
                'trendyol_product_id' => $row['شناسه جایگزین ترندیول'],
            ]
        );

        Log::info('product-import-update', [
            'before' => $result->getOriginal(),
            'after' => $result->getChanges(),
            'data' => $row,
        ]);

        return $result;
    }

    private function validate($row): bool
    {
        $result = true;

        if (empty($row['شناسه تنوع زیتازی'])) {
            $result = false;
        }

        $variationCheck = Variation::where([
            'own_id' => $row['شناسه تنوع زیتازی'],
        ])->whereNot([
            'sku' => $row['شناسه تنوع دکلتون'],
        ])->exists();

        if ($variationCheck) {
            $result = false;
            Session::push('import_errors', [
                'message' => 'شناسه تنوع زیتازی تکراری است: ' . $row['شناسه تنوع دکلتون'],
            ]);
        }

        return $result;
    }
}
