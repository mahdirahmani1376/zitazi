<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Enums\SourceEnum;
use App\Services\WoocommerceService;
use Illuminate\Database\Seeder;
use Automattic\WooCommerce\Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $path = database_path('seeders/data/data.csv');
        // $file = $this->readCSV($path);
        Product::truncate();

        $sheetUrl = "https://docs.google.com/spreadsheets/d/1TUpUwYKVIIc3z7fQk3RVvSm08Kg9rJnB-YiYkFJSawg/gviz/tq?tqx=out:csv";
        $response = Http::get($sheetUrl);
        $csvData = $response->body();
        $data = parse_csv($csvData);

        $bar = $this->command->getOutput()->progressStart(count($data));
        
        foreach ($data as $key => $value)
        {
            if (! empty($value) &&  ! empty($value['Trendyol-link']))
            {
                $product = Product::query()->create([
                        'own_id' => $value['Woocomerce-ID'],
                        'source_id' => $value['Trendyol-link'],
                        'source' => SourceEnum::TRENDYOL->value
                ]);

                $this->syncProduct($product);

            }

            else if (! empty($value) && ! empty($value['digikala_link']))
            {
                $product = Product::query()->create([
                    'own_id' => $value['Woocomerce-ID'],
                    'digikala_source' => $value['digikala_link'],
                    'torob_source' => urldecode($value['torob_link']),
                    'source' => SourceEnum::IRAN->value
            ]);

            $this->syncProduct($product);
            }

            $this->command->getOutput()->progressAdvance();

        }

        $this->command->getOutput()->progressFinish();


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

    private function syncProduct($product)
    {
        $woocommerce = WoocommerceService::getClient();
        
        try {
            $response = $woocommerce->get("products/{$product->own_id}");
            $price = !empty($response?->price) ? $response?->price : null;
            $stock = $response?->stock_status == "instock" ? 5 : 0;
            $product->update([
                'rial_price' => $price,
                'stock' => $stock
            ]);
        } catch (\Exception $e)
        {
            $product->delete();
        }
    }
}
