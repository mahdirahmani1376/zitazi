<?php

namespace Database\Seeders;

use App\Jobs\SyncChunkProductsWithZitaziJob;
use App\Models\Product;
use Illuminate\Bus\Batch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
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
        $sheetUrl = 'https://sheets.googleapis.com/v4/spreadsheets/1TUpUwYKVIIc3z7fQk3RVvSm08Kg9rJnB-YiYkFJSawg/values/Sheet1?valueRenderOption=FORMATTED_VALUE&key=' . env('GOOGLE_SHEET_API_KEY');
        $response = Http::acceptJson()->get($sheetUrl);
        $csvData = $response->json()['values'];
        $data = parse_sheet_response($csvData);

        $productsToUpdate = [];

        foreach ($data as $key => $value) {
            try {
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
                    'markup' => is_numeric($value['Mark-up']) ? $value['Mark-up'] : null,
                    'category' => data_get($value, 'Category'),
                    'brand' => data_get($value, 'Brand'),
                    'owner' => data_get($value, 'Owner'),
                    'product_name' => data_get($value, 'Product Name'),
                    'decathlon_url' => data_get($value, 'Decathlon_link'),
                    'decathlon_id' => data_get($value, 'decathlon_id'),
                    'elele_source' => data_get($value, 'Elele_link'),
                    'promotion' => !empty($value['Promotion']) ? $value['Promotion'] : 0,
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
                        'markup',
                        'category',
                        'brand',
                        'owner',
                        'product_name',
                        'decathlon_url',
                        'decathlon_id',
                        'elele_source',
                        'promotion',
                        'updated_at',
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
        $products = Product::pluck('own_id');

        $startTime = microtime(true);

        $jobs = $products->chunk(16)->map(function (Collection $batch) {
            try {
                return new SyncChunkProductsWithZitaziJob($batch);
            } catch (Throwable $e) {
                dump($e->getMessage());
                Log::error($e->getMessage());
                $result = [];
            }

            $this->command->getOutput()->progressAdvance();

            return $result;
        });

        Bus::batch($jobs)
            ->then(function () use ($startTime) {
                $endTime = microtime(true);

                $duration = $endTime - $startTime;
                $text = 'Finished sync products at ' . Carbon::now()->toDateTimeString() .
                    '. Duration: ' . number_format($duration, 2) . ' seconds.';
                Log::info($text);
            })
            ->catch(function (Batch $batch, Throwable $e) {
                Log::error('seed products failed', [
                    'error' => $e->getMessage(),
                ]);
            })
            ->name('Seed products')
            ->dispatch();

    }
}
