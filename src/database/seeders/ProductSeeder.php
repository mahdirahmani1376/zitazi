<?php

namespace Database\Seeders;

use App\DTO\ZitaziUpdateDTO;
use App\Jobs\SyncZitaziJob;
use App\Models\Product;
use Illuminate\Database\Seeder;
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
//        $this->seedZitaziProducts();
        $this->seedSatreProducts();

    }

    public function seedZitaziProducts(): void
    {
        $sheetUrl = 'https://sheets.googleapis.com/v4/spreadsheets/1TUpUwYKVIIc3z7fQk3RVvSm08Kg9rJnB-YiYkFJSawg/values/Sheet1?valueRenderOption=FORMATTED_VALUE&key=' . env('GOOGLE_SHEET_API_KEY');
        $response = Http::acceptJson()->get($sheetUrl);
        $csvData = $response->json()['values'];
        $data = parse_sheet_response($csvData);
        $allOwnIds = collect($data)->pluck('Woocomerce-ID');

        Product::whereNotIn('own_id', $allOwnIds)->each(function ($product) use ($allOwnIds) {
            foreach ($product->variations as $variation) {
                $variation->delete();
                $updateData = ZitaziUpdateDTO::createFromArray([
                    'stock_quantity' => 0,
                ]);

                SyncZitaziJob::dispatch($variation, $updateData);

                $variation->update(['own_id' => 0]);
                Log::info('variation deleted', [
                    'variation_id' => $product->id,
                ]);
            }
            $product->delete();
            Log::info('product deleted', [
                'product_id' => $product->id,
                'product_own_id' => $product->own_id,
            ]);
        });

        foreach ($data as $key => $value) {
            try {
                $minPrice = null;
                if (
                    !empty($value['min_price'])
                    && is_numeric($value['min_price'])
                ) {
                    $minPrice = (int)$value['min_price'];
                }

                $ownId = data_get($value, 'Woocomerce-ID');

                $data = [
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
                    'matilda_source' => data_get($value, 'matilda_source'),
                    'sazkala_source' => data_get($value, 'sazkala_url'),
                    'amazon_source' => !empty($value) ? data_get(explode('/', data_get($value, 'amazon_source')), 4) : null,
                    'promotion' => !empty($value['Promotion']) ? $value['Promotion'] : 0,
                    'updated_at' => now()->toDateString(),
                    'created_at' => now()->toDateString(),
                    'base_source' => Product::ZITAZI,
                ];

                Product::updateOrCreate(['own_id' => $ownId], $data);

            } catch (Throwable $e) {
                dump($e->getMessage());
                Log::error($e->getMessage());
            }
        }


    }

    public function seedSatreProducts(): void
    {
        $client = new \Google\Client();
        $client->setAuthConfig(storage_path('app/private/google_key.json'));
        $client->addScope(\Google\Service\Sheets::SPREADSHEETS_READONLY);

        $service = new \Google\Service\Sheets($client);

        $spreadsheetId = '1BdY8s2jQ_VcNYlHSIZCSVo1_IfewB7xGNJYNiyF4SpA';
        $range = 'ساتره';

        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $rows = $response->getValues();
        $data = parse_sheet_response($rows);

        foreach ($data as $key => $value) {
            try {
                $ownId = data_get($value, 'Woocomerce-ID');

                $data = [
                    'trendyol_source' => data_get($value, 'Trendyol-link'),
                    'markup' => is_numeric($value['Mark-up']) ? $value['Mark-up'] : null,
                    'category' => data_get($value, 'Category'),
                    'brand' => data_get($value, 'Brand'),
                    'owner' => data_get($value, 'Owner'),
                    'product_name' => data_get($value, 'Name'),
                    'base_source' => Product::SATRE,
                ];

                Product::updateOrCreate(['own_id' => $ownId], $data);

            } catch (Throwable $e) {
                dump($e->getMessage());
                Log::error($e->getMessage());
            }
        }


    }

}
