<?php

namespace App\Imports;

use App\Models\Variation;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ImportDecathlonVariation implements ToModel,WithHeadingRow
{
    public function model(array $row)
    {
        $result =  Variation::updateOrCreate(
            ['id' => $row['شناسه تنوع در وب سرویس']],
            [
                'own_id' => $row['شناسه تنوع زیتازی'],
                'trendyol_product_id' => $row['شناسه جایگزین ترندیول'],
            ]
        );

        Log::info("product-import-update",[
            'before' => $result->getOriginal(),
            'after' => $result->getChanges(),
            'data' => $row
        ]);

        return $result;
    }
}
