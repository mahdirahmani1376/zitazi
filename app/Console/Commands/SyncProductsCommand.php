<?php

namespace App\Console\Commands;

use App\Actions\SyncProductsAction;
use App\Models\Product;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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

        $products = Product::all();

        $bar = $this->output->createProgressBar($products->count());


        foreach ($products as $product) {
            try {
                $syncAction($product);
            } catch (Exception $e) {
                dump($e->getMessage());
                Log::error("product_update_failed_id:{$product->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
            $bar->advance();
        }

        $bar->finish();

        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        Log::info('Finished app:sync-products at '.Carbon::now()->toDateTimeString().
            '. Duration: '.number_format($duration, 2).' seconds.');

        return 0;

    }
}
