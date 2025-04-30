<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncChunkProductsWithZitaziJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $backoff = 10;

    public function __construct(
        private Collection $batch
    )
    {
    }

    public function handle(): void
    {
        $batch = $this->batch;

        $responses = Http::pool(function (Pool $pool) use ($batch) {
            return $batch->map(function ($ownId) use ($pool) {
                return $pool
                    ->withBasicAuth(env('SECURITY_KEY'), env('SECURITY_PASS'))
                    ->get("https://zitazi.com/wp-json/wc/v3/products/{$ownId}");
            });
        });

        $results = [];
        collect($responses)->each(function (Response $response) use (&$results) {
            try {
                $response = $response->json();

                $price = null;
                if (!empty($response['price'])) {
                    $price = $response['price'];
                }

                $stock = $response['stock_status'] == 'instock' ? 5 : 0;
                $results[] = [
                    'own_id' => (int)$response['id'],
                    'rial_price' => $price,
                    'stock' => $stock,
                    'updated_at' => now()->toDateString(),
                ];
            } catch (Throwable $e) {
                dump($e->getMessage());
                Log::error($e->getMessage());
            }
        });

        try {
            DB::table('products')->upsert($results, ['own_id'], ['rial_price', 'stock', 'updated_at']);
        } catch (Throwable $e) {
            dump($e->getMessage());
            Log::error($e->getMessage());
        }

    }
}
