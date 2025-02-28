<?php

namespace Database\Seeders;

use App\Enums\SourceEnum;
use App\Models\Product;
use App\Services\WoocommerceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $path = database_path('seeders/data/data.csv');
        // $file = $this->readCSV($path);
        // Product::truncate();

        $sheetUrl = "https://docs.google.com/spreadsheets/d/1TUpUwYKVIIc3z7fQk3RVvSm08Kg9rJnB-YiYkFJSawg/gviz/tq?tqx=out:csv";
        $response = Http::get($sheetUrl);
        $csvData = $response->body();
        $data = parse_csv($csvData);

        $this->command->getOutput()->progressStart(count($data));

        foreach ($data as $key => $value) {
            $createData = [
                'digikala_source' => $value['digikala_dkp'] ?? null,
                'trendyol_source' => $value['Trendyol-link'] ?? null,
                'torob_source'    => urldecode($value['torob_link']) ?? null,
            ];
            $product = Product::query()->updateOrCreate(
                [
                    'own_id'    => $value['Woocomerce-ID']
                ],
                $createData
            );

            if (! empty($product->trendyol_source))
            {
                $this->syncProduct($product);
            }
            $this->command->getOutput()->progressAdvance();
            \DB::disconnect();
        }

        $this->command->getOutput()->progressFinish();


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
                'stock'      => $stock
            ]);
        } catch (\Exception $e) {
            $product->delete();
        }
    }
}
