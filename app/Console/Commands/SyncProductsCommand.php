<?php

namespace App\Console\Commands;

use App\Actions\SyncProductsAction;
use App\Jobs\SheetReportJob;
use App\Jobs\SyncProductJob;
use App\Models\Product;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

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
        $startTime = microtime(true);

        $syncAction = app(SyncProductsAction::class);

        if (! empty($this->option('override-id'))) {
            $product = Product::find($this->option('override-id'));
            if ($product->belongsToTrendyol()) {
                $syncAction->syncTrendyol($product);
            }
            if ($product->belongsToElele()) {
                $syncAction->syncElele($product);
            }
            if ($product->belongsToIran()) {
                $syncAction->syncIran($product);
            }

            return 0;
        }

        $jobs = Product::all()->map(fn($product) => new SyncProductJob($product));

        Bus::batch($jobs)
            ->then(fn() => $this->info('All products updated successfully.'))
            ->catch(fn() => $this->error('Some jobs failed.'))
            ->dispatch();

        $endTime = microtime(true);

        $duration = $endTime - $startTime;
        $text = 'Finished app:sync-products at '.Carbon::now()->toDateTimeString().
            '. Duration: '.number_format($duration, 2).' seconds.';
        $this->info($text);
        Log::info($text);

//        return 0;

    }
}
