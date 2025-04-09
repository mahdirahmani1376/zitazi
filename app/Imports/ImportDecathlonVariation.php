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


        $variation = Variation::where([
            ['sku' => $row['شناسه تنوع دکلتون']]
        ])->first();

        if ($variation) {
            $ownIdCheck = Variation::where(
                [
                    'own_id' => $row['شناسه تنوع زیتازی'],
                ]
            )->first();

            if ($ownIdCheck->id != $variation->id) {
                $result = false;
                Session::push('import_errors', [
                    'message' => 'شناسه تنوع زیتازی تکراری است اما با SKU متفاوتی مرتبط شده.',
                    'own_id' => $row['شناسه تنوع زیتازی'],
                ]);
            }
        }

        return $result;
    }
}
