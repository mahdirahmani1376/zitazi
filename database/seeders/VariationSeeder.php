<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Variation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class VariationSeeder extends Seeder
{
    public function run(): void
    {
//        Variation::truncate();
//        $this->seedVariations();
        $this->syncVariations();
    }

    private function seedVariations(): void
    {
        $sheetUrl = 'https://sheets.googleapis.com/v4/spreadsheets/1TUpUwYKVIIc3z7fQk3RVvSm08Kg9rJnB-YiYkFJSawg/values/Sheet3?valueRenderOption=FORMATTED_VALUE&key=' . env('GOOGLE_SHEET_API_KEY');
        $response = Http::acceptJson()->get($sheetUrl);
        $csvData = $response->json()['values'];
        $data = parse_sheet_response($csvData);

        $variationData = [];

        $data = collect($data)
            ->filter(function ($item) {
                return $item['Product Type'] == 'variable'
                    &&
                    !empty($item['Parent Product ID'])
                    &&
                    !empty($item['ID']);
            })->each(function ($item) use (&$variationData) {
                $product = Product::firstWhere('own_id', '=', $item['Parent Product ID']);

                if (!empty($product)) {
                    $variationData[] = [
                        'own_id' => $item['ID'],
                        'product_id' => $product->id,
                        'updated_at' => now()->toDateString(),
                        'created_at' => now()->toDateString(),
                    ];
                }
            });


        $batchSize = 10;
        $chunks = array_chunk($variationData, $batchSize);
        $this->command->getOutput()->progressStart(count($chunks));

        foreach ($chunks as $chunk) {
            try {
                DB::table('variations')->upsert(
                    $chunk,
                    ['own_id'],
                    [
                        'product_id',
                        'updated_at',
                        'created_at'
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

    private function syncVariations(): void
    {
        $variations = Variation::withWhereHas('product', function ($q) {
            $q->where(function (Builder $query) {
                $query->WhereNot('decathlon_url','=','');
            });
        })
        ->limit(1)
        ->get()
        ->chunk(16);

        $bar = $this->command->getOutput()->createProgressBar(count($variations));

        $responses = $variations->map(function (Collection $chunk) use ($bar) {
            $result = Http::pool(function (Pool $pool) use ($chunk) {
                return $chunk->map(function (Variation $variation) use ($pool) {
                    return $pool->get($variation->product->decathlon_url);
                });
            });

            $bar->advance();

            return $result;
        });

        $responses = $responses->collapse()->map(function ($item){
            return $item->body();
        });

        dd($responses->toArray());


    }

}
