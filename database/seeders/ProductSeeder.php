<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedProducts();

        $this->syncProducts();
    }

    public function seedProducts(): void
    {
        $sheetUrl = "https://sheets.googleapis.com/v4/spreadsheets/1TUpUwYKVIIc3z7fQk3RVvSm08Kg9rJnB-YiYkFJSawg/values/Sheet1?valueRenderOption=FORMATTED_VALUE&key=" . env('GOOGLE_SHEET_API_KEY');
        $response = Http::acceptJson()->get($sheetUrl);
        $csvData = $response->json()['values'];
        $data = parse_sheet_response($csvData);

//        $data = array_slice($data, 0, 10);

        $productsToUpdate = [];

        foreach ($data as $key => $value) {

            $minPrice = null;
            if (
                !empty($value['min_price'])
                && is_numeric($value['min_price'])
            ) {
                $minPrice = (int)$value['min_price'];
            }

            $productsToUpdate[] = [
                'own_id' => data_get($value, 'Woocomerce-ID'),
                'trendyol_source' => data_get($value, 'Trendyol-link'),
                'digikala_source' => data_get($value, 'digikala_dkp'),
                'torob_source' => urldecode(data_get($value, 'torob_link')),
                'torob_id' => !empty($value) ? data_get(explode('/', urldecode(data_get($value, 'torob_link'))), 4) : null,
                'min_price' => $minPrice,
                'category' => data_get($value, 'Category'),
                'brand' => data_get($value, 'Brand'),
                'owner' => data_get($value, 'Owner'),
                'product_name' => data_get($value, 'Product Name'),
                'decathlon_url' => data_get($value, 'Decathlon_link'),
                'decathlon_id' => data_get($value, 'decathlon_id'),
            ];
        }

        $batchSize = 10;
        $chunks = array_chunk($productsToUpdate, $batchSize);
        $this->command->getOutput()->progressStart(count($chunks));
        foreach ($chunks as $chunk) {
            DB::table('products')->upsert(
                $chunk,
                ['own_id'],
                [
                    'trendyol_source',
                    'digikala_source',
                    'torob_source',
                    'torob_id',
                    'min_price',
                    'category',
                    'brand',
                    'owner',
                    'product_name',
                    'decathlon_url',
                    'decathlon_id',
                    'updated_at'
                ]
            );

            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();
    }

    private function syncProducts(): void
    {
        $products = Product::pluck('own_id');

        $this->command->getOutput()->progressStart((int) count($products) / 16);

        $responses = $products->chunk(16)->map(function (Collection $batch) {
            $result =  Http::pool(function (Pool $pool) use ($batch) {
                return $batch->map(function (Product $item) use ($pool) {
                    return $pool
                        ->withBasicAuth(env('SECURITY_KEY'),env('SECURITY_PASS'))
                        ->get("https://zitazi.com/wp-json/wc/v3/products/{$item->own_id}");
                });
            });
            $this->command->getOutput()->progressAdvance();
            return $result;
        });

        $results = [];
        collect($responses)->collapse()->each(function (Response $response) use (&$results) {
           $response = $response->json();
           $price = ! empty($response['price']) || $response['price'] != '' ? $response['price'] : null;
           $stock = $response['stock_status'] == 'instock' ? 5 : 0;
           $results[] = [
               'own_id' => (int) $response['id'],
               'rial_price' => $price,
               'stock' => $stock,
            ];

        });

        $this->command->getOutput()->progressFinish();


        $this->command->getOutput()->progressStart((int) count($results) / 20);


        foreach (array_chunk($results,20) as $chunk) {
            $updateCases = '';
            $updateStockCases = '';

            $ids = [];
            foreach ($chunk as $value) {
                $id = $value['own_id'];
                $stock = $value['stock'];
                $rialPrice = $value['rial_price'];

                $updateCases .= "WHEN own_id = '$id' THEN '$rialPrice'";
                $updateStockCases .= "WHEN own_id = '$id' THEN '$stock'";
                $ids[] = $id;
            }

            $idsList = implode(',', $ids);

            $sql = "
            UPDATE products
            SET
                price = CASE $updateCases END,
                stock = CASE $updateStockCases END
            WHERE own_id IN ($idsList)
            ";

            DB::statement($sql);

            $this->command->getOutput()->progressAdvance();

        }


        $this->command->getOutput()->progressFinish();


    }
}
