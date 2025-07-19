<?php

namespace Database\Seeders;

use App\Jobs\SeedVariationsForProductJob;
use App\Models\Product;
use Illuminate\Bus\Batch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class VariationSeeder extends Seeder
{
    public function run(): void
    {
        $startTime = microtime(true);

        $jobs = Product::query()
            ->whereNot('decathlon_url', '=', '')
            ->orWhereNot('trendyol_source', '=', '')
            ->orWhereNot('amazon_source', '=', '')
            ->whereIn('own_id',[
                805722,805723
            ])
            ->ddRawSql()
            ->get()
            ->map(fn($product) => new SeedVariationsForProductJob($product));

        Bus::batch($jobs)
            ->then(function () use ($startTime) {
                $endTime = microtime(true);

                $duration = $endTime - $startTime;
                $text = 'Finished seed variations at '.Carbon::now()->toDateTimeString().
                    '. Duration: '.number_format($duration, 2).' seconds.';
                Log::info($text);
            })
            ->catch(function (Batch $batch, Throwable $e) {
                Log::error('seed variations failed', [
                    'error' => $e->getMessage(),
                ]);
            })
            ->name('Seed variations')
            ->dispatch();

    }
}
