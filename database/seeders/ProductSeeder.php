<?php

namespace Database\Seeders;

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

        $sheetUrl = 'https://docs.google.com/spreadsheets/d/1TUpUwYKVIIc3z7fQk3RVvSm08Kg9rJnB-YiYkFJSawg/gviz/tq?tqx=out:csv';
        $response = Http::get($sheetUrl);
        $csvData = $response->body();
        $data = parse_csv($csvData);

        $this->command->getOutput()->progressStart(count($data));

        foreach ($data as $key => $value) {

            $createData = [
                'digikala_source' => data_get($value, 'digikala_dkp'),
                'trendyol_source' => data_get($value, 'Trendyol-link'),
                'torob_source' => urldecode(data_get($value, 'torob_link')),
                'torob_id' => !empty($value) ? data_get(explode('/',urldecode(data_get($value, 'torob_link'))),4) : null,
                'min_price' => ! empty(data_get($value, 'Minimum_Price')) ? data_get($value, 'Minimum_Price') : null,
                'category' => data_get($value, 'Category'),
                'brand' => data_get($value, 'Brand'),
                'owner' => data_get($value, 'Owner'),
                'product_name' => data_get($value, 'Product Name'),
                'decathlon_url' => data_get($value, 'Decathlon_link'),
            ];

            $product = Product::query()->updateOrCreate(
                [
                    'own_id' => $value['Woocomerce-ID'],
                ],
                $createData
            );

            $this->syncProduct($product);

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
            $price = ! empty($response?->price) ? $response?->price : null;
            $stock = $response?->stock_status == 'instock' ? 5 : 0;
            $product->update([
                'rial_price' => $price,
                'stock' => $stock,
            ]);
        } catch (\Exception $e) {
            $product->delete();
        }
    }
}
