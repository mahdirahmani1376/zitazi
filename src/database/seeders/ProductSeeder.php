<?php

namespace Database\Seeders;

use App\DTO\ZitaziUpdateDTO;
use App\Jobs\SyncZitaziJob;
use App\Models\Product;
use Google\Client;
use Google\Service\Sheets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductSeeder extends Seeder
{
    public array $notFoundProductIds = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedZitaziProducts();
        $this->seedSatreProducts();

        $this->deleteNotFoundProducts();

    }

    public function seedZitaziProducts(): void
    {
        $sheetUrl = 'https://sheets.googleapis.com/v4/spreadsheets/1TUpUwYKVIIc3z7fQk3RVvSm08Kg9rJnB-YiYkFJSawg/values/Sheet1!A:Z?valueRenderOption=FORMATTED_VALUE&key='.env('GOOGLE_SHEET_API_KEY');
        $response = Http::acceptJson()->get($sheetUrl);
        $csvData = $response->json()['values'];
        $data = parse_sheet_response($csvData);
        $allOwnIds = [];

        foreach ($data as $key => $value) {
            try {
                $minPrice = null;
                if (
                    ! empty($value['min_price'])
                    && is_numeric($value['min_price'])
                ) {
                    $minPrice = (int) $value['min_price'];
                }

                $ownId = data_get($value, 'Woocomerce-ID');
                if (empty($ownId)) {
                    Log::error('no own id for selected row', [
                        'value' => $value,
                    ]);

                    continue;
                }
                $allOwnIds[] = $ownId;

                $value = array_map(function ($item) {
                    return empty(trim($item)) ? null : $item;
                }, $value);

                $data = [
                    'min_price' => $minPrice,
                    'markup' => is_numeric($value['Mark-up']) ? $value['Mark-up'] : null,
                    'category' => data_get($value, 'Category'),
                    'brand' => data_get($value, 'Brand'),
                    'owner' => data_get($value, 'Owner'),
                    'product_name' => data_get($value, 'Product Name'),
                    'trendyol_source' => data_get($value, 'Trendyol-link'),
                    'decathlon_url' => data_get($value, 'Decathlon_link'),
                    'decathlon_id' => data_get($value, 'decathlon_id'),
                    'digikala_source' => data_get($value, 'digikala_dkp'),
                    'torob_source' => urldecode(data_get($value, 'torob_link')),
                    'torob_id' => ! empty($value) ? data_get(explode('/', urldecode(data_get($value, 'torob_link'))), 4) : null,
                    'elele_source' => data_get($value, 'Elele_link'),
                    'matilda_source' => data_get($value, 'matilda_source'),
                    'sazkala_source' => data_get($value, 'sazkala_url'),
                    'amazon_source' => ! empty($value) ? data_get(explode('/', data_get($value, 'amazon_source')), 4) : null,
                    'promotion' => ! empty($value['Promotion']) ? 1 : 0,
                    'updated_at' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(),
                    'base_source' => Product::ZITAZI,
                ];

                Product::updateOrCreate(['own_id' => $ownId], $data);

            } catch (Throwable $e) {
                Log::error('product seeder error', [
                    'exception_message' => $e->getMessage(),
                    'value' => $value,
                ]);
            }
        }

        Product::query()->where('base_source', Product::ZITAZI)->whereNotIn('own_id', $allOwnIds)->each(function ($product) {
            $this->notFoundProductIds[] = $product->id;
            Log::error('product seeder not found', [
                'product_id ' => $product->id,
            ]);
        });

    }

    public function seedSatreProducts(): void
    {
        $client = new Client;
        $client->setAuthConfig(storage_path('app/private/google_key.json'));
        $client->addScope(Sheets::SPREADSHEETS_READONLY);

        $service = new Sheets($client);

        $spreadsheetId = '1BdY8s2jQ_VcNYlHSIZCSVo1_IfewB7xGNJYNiyF4SpA';
        $range = 'Sheet1!A:Z';

        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $rows = $response->getValues();
        $data = parse_sheet_response($rows);
        $allOwnIds = [];

        foreach ($data as $key => $value) {
            try {
                $ownId = data_get($value, 'Woocomerce-ID');
                if (empty($ownId)) {
                    Log::error('no own id for selected row', [
                        'value' => $value,
                    ]);

                    continue;
                }
                $allOwnIds[] = $ownId;

                $value = array_map(function ($item) {
                    return empty(trim($item)) ? null : $item;
                }, $value);

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
                Log::error('product seeder error', [
                    'exception_message' => $e->getMessage(),
                    'value' => $value,
                ]);
            }
        }

        Product::query()->where('base_source', Product::SATRE)->whereNotIn('own_id', $allOwnIds)->each(function ($product) {
            $this->notFoundProductIds[] = $product->id;
            Log::error('product seeder not found', [
                'product_id' => $product->id,
            ]);
        });

    }

    /**
     * @return void
     */
    private function deleteNotFoundProducts(): void
    {
        if (!empty($this->notFoundProductIds)) {
            Log::error('not found product Ids', [
                'data' => $this->notFoundProductIds,
            ]);

            foreach (Product::with('variations')
                         ->whereIn('id', $this->notFoundProductIds)
                         ->get() as $product) {
                foreach ($product->variations as $variation) {
                    $variation->update([
                        'stock' => 0,
                    ]);

                    SyncZitaziJob::dispatch($variation, ZitaziUpdateDTO::createFromArray([
                        'stock_quantity' => 0,
                    ]));

                    Log::info('variation deleted', [
                        'variation_id' => $variation->id,
                        'woocommerce_id' => $variation->product->own_id,
                        'data' => $variation->toArray(),
                    ]);

                    $variation->delete();
                }

                Log::info('product deleted', [
                    'product_id' => $product->id,
                    'woocommerce_id' => $product->own_id,
                    'data' => $product->toArray(),
                ]);

                $product->delete();

            }
        }
    }
}
