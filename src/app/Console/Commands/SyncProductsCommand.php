<?php

namespace App\Console\Commands;

use App\Actions\SyncProductsAction;
use App\Jobs\SyncProductJob;
use App\Models\Product;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-products {--not-sync} {--override-id=} {--d}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Cache::forget(Product::TOROB_LOCK_FOR_UPDATE);

        $startTime = microtime(true);

        $syncAction = app(SyncProductsAction::class);

        if (! empty($this->option('override-id'))) {
            $product = Product::find($this->option('override-id'));
            $syncAction->execute($product);

            return 0;
        }

        $jobs = Product::query()
            ->where('decathlon_url', '=', '')
            ->where('trendyol_source', '=', '')
            ->get()
            ->map(fn($product) => new SyncProductJob($product));

        Bus::batch($jobs)
            ->then(function () use ($startTime) {
                $endTime = microtime(true);

                $duration = $endTime - $startTime;
                $text = 'Finished app:sync-products at '.Carbon::now()->toDateTimeString().
                    '. Duration: '.number_format($duration, 2).' seconds.';
                Log::info($text);
            })
            ->catch(function (Batch $batch, Throwable $e) {
                Log::error('app:sync-products failed', [
                    'error' => $e->getMessage(),
                ]);
            })
            ->name('Import Products')
            ->dispatch();

        return 0;

    }
}
