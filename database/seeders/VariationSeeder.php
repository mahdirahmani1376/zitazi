<?php

namespace Database\Seeders;

use App\Jobs\SeedVariationsForProductJob;
use App\Jobs\SyncProductJob;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

class VariationSeeder extends Seeder
{
    public function run(): void
    {
        $startTime = microtime(true);

        $rate = Currency::syncTryRate();

        $jobs = Product::all()->map(fn ($product) => new SeedVariationsForProductJob($product,$rate));

        Bus::batch($jobs)
            ->then(function () use ($startTime) {
                $endTime = microtime(true);

                $duration = $endTime - $startTime;
                $text = 'Finished seed variations at '.Carbon::now()->toDateTimeString().
                    '. Duration: '.number_format($duration, 2).' seconds.';
                Log::info($text);
            })
            ->catch(function (\Throwable $e) {
                Log::error('seed variations failed', [
                    'error' => $e->getMessage(),
                ]);
            })
            ->name('Seed variations')
            ->dispatch();


    }

}
