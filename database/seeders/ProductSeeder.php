<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        $sheetUrl = 'https://sheets.googleapis.com/v4/spreadsheets/1TUpUwYKVIIc3z7fQk3RVvSm08Kg9rJnB-YiYkFJSawg/values/Sheet1?valueRenderOption=FORMATTED_VALUE&key='.env('GOOGLE_SHEET_API_KEY');
        $response = Http::acceptJson()->get($sheetUrl);
        $csvData = $response->json()['values'];
        $data = parse_sheet_response($csvData);

        $productsToUpdate = [];

        foreach ($data as $key => $value) {
            try {
                $minPrice = null;
                if (
                    ! empty($value['min_price'])
                    && is_numeric($value['min_price'])
                ) {
                    $minPrice = (int) $value['min_price'];
                }

                $productsToUpdate[] = [
                    'own_id' => data_get($value, 'Woocomerce-ID'),
                    'trendyol_source' => data_get($value, 'Trendyol-link'),
                    'digikala_source' => data_get($value, 'digikala_dkp'),
                    'torob_source' => urldecode(data_get($value, 'torob_link')),
                    'torob_id' => ! empty($value) ? data_get(explode('/', urldecode(data_get($value, 'torob_link'))), 4) : null,
                    'min_price' => $minPrice,
                    'category' => data_get($value, 'Category'),
                    'brand' => data_get($value, 'Brand'),
                    'owner' => data_get($value, 'Owner'),
                    'product_name' => data_get($value, 'Product Name'),
                    'decathlon_url' => data_get($value, 'Decathlon_link'),
                    'decathlon_id' => data_get($value, 'decathlon_id'),
                    'elele_source' => data_get($value, 'Elele_link'),
                    'promotion' => ! empty($value['Promotion']) ? $value['Promotion'] : 0,
                    'updated_at' => now()->toDateString(),
                ];
            } catch (Throwable $e) {
                dump($e->getMessage());
                Log::error($e->getMessage());
            }
        }

        $batchSize = 10;
        $chunks = array_chunk($productsToUpdate, $batchSize);
        $this->command->getOutput()->progressStart(count($chunks));
        foreach ($chunks as $chunk) {
            try {
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
                        'elele_source',
                        'updated_at',
                        'promotion',
                    ]
                );
            } catch (Throwable $e) {
                dump($e->getMessage());
                Log::error($e->getMessage());
            }

            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();
    }

    private function syncProducts(): void
    {
        $products = Product::limit(1)->pluck('own_id');

        $this->command->getOutput()->progressStart((int) count($products) / 16);

        $responses = $products->chunk(16)->map(function (Collection $batch) {
            try {
                $result = Http::pool(function (Pool $pool) use ($batch) {
                    return $batch->map(function ($ownId) use ($pool) {
                        return $pool
                            ->withBasicAuth(env('SECURITY_KEY'), env('SECURITY_PASS'))
                            ->get("https://zitazi.com/wp-json/wc/v3/products/{$ownId}");
                    });
                });
            } catch (Throwable $e) {
                dump($e->getMessage());
                Log::error($e->getMessage());
                $result = [];
            }

            $this->command->getOutput()->progressAdvance();

            return $result;
        });

        $results = [];
        collect($responses)->collapse()->each(function (Response $response) use (&$results) {
            try {
                $response = $response->json();

                $price = null;
                if (! empty($response['price'])) {
                    $price = $response['price'];
                }

                $stock = $response['stock_status'] == 'instock' ? 5 : 0;
                $results[] = [
                    'own_id' => (int) $response['id'],
                    'rial_price' => $price,
                    'stock' => $stock,
                    'updated_at' => now()->toDateString(),
                ];
            } catch (Throwable $e) {
                dump($e->getMessage());
                Log::error($e->getMessage());
            }
        });

        $this->command->getOutput()->progressFinish();

        $batchSize = 50;
        $this->command->getOutput()->progressStart((int) count($results) / $batchSize);

        foreach (array_chunk($results, $batchSize) as $chunk) {
            try {
                DB::table('products')->upsert($chunk, ['own_id'], ['rial_price', 'stock', 'updated_at']);
            } catch (Throwable $e) {
                dump($e->getMessage());
                Log::error($e->getMessage());
            }
            $this->command->getOutput()->progressAdvance();
        }

        $this->command->getOutput()->progressFinish();

    }
}
