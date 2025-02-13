<?php

namespace Database\Seeders;

use App\Enums\SourceEnum;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/tr-lego.csv');
        $file = $this->readCSV($path);
        foreach ($file as $key => $value)
        {
            if (! empty($value))
            {
                Product::query()->updateOrCreate([
                    'own_id' => $value['own_id']
                ],[
                    'own_id' => $value['own_id'],
                    'source_id' => $value['source_id'],
                    'source' => SourceEnum::TRENDYOL->value
                ]);
            }
        }

    }

    public function readCSV($csvFile, $delimiter = ',')
    {
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            $headers = fgetcsv($handle); // Read the first line as headers
            $data = [];

            while (($row = fgetcsv($handle)) !== FALSE) {
                if (! empty($row[0]))
                {
                    $data[] = array_combine($headers, $row); // Combine headers with row values
                }
            }

            fclose($handle);

            return $data;
        }
    }
}
