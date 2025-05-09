<?php

namespace App\Console\Commands;

use App\Jobs\SyncVariationsJob;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class SyncVariationCommand extends Command
{
    protected $signature = 'app:sync-variations {--sync=true}';

    protected $description = 'Command description';

    public function handle(): void
    {
        $sync = $this->option('sync') == true;

        $jobs = Variation::query()
            ->where(function (Builder $query) {
                $query
                    ->whereNot('url', '=', '')
                    ->where(function (Builder $query) {
                        $query
                            ->whereNotNull('own_id')
                            ->orWhere('item_type', '=', Product::PRODUCT_UPDATE);
                    });
            })
            ->get()
            ->map(function (Variation $variation) {
                return new SyncVariationsJob($variation);
            });

        Bus::batch($jobs)
            ->then(fn () => Log::info('All variations updated successfully.'))
            ->catch(fn () => Log::error('Some jobs failed.'))
            ->name('Import Variations')
            ->dispatch();
    }
}
