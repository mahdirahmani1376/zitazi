<?php

namespace Database\Seeders;

use App\Jobs\SeedVariationsForProductJob;
use App\Models\Currency;
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

        $rate = Currency::syncTryRate();

        $jobs = Product::query()
            ->whereNot('decathlon_url', '=', '')
            ->get()
            ->map(fn($product) => new SeedVariationsForProductJob($product, $rate));

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
