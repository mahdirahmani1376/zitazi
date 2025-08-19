<?php

namespace App\Console\Commands;

use App\DTO\ZitaziUpdateDTO;
use App\Jobs\SyncZitaziJob;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class SyncZitaziCommand extends Command
{
    protected $signature = 'app:sync-zitazi';

    protected $description = 'sync all zitazi decathlon variations';

    public function handle(): void
    {
        $variations = Variation::query()
            ->where(function (Builder $query) {
                $query
                    ->whereNot('own_id', '')
                    ->orWhere('item_type', Product::PRODUCT_UPDATE);
            })
            ->whereHas('product', function (Builder $query) {
                $query->whereNot('promotion', 1);
            })
            ->get();

        $jobs = [];
        foreach ($variations as $variation) {
            if ($variation->status == Variation::AVAILABLE) {
                $updateData = ZitaziUpdateDTO::createFromArray([
                    'stock_quantity' => $variation->stock,
                    'price' => $variation->rial_price,
                ]);
            } else {
                $updateData = ZitaziUpdateDTO::createFromArray([
                    'stock_quantity' => 0,
                ]);
            }

            $jobs[] = new SyncZitaziJob($variation, $updateData);
        }

        Bus::batch($jobs)
            ->then(fn() => Log::info('All variations synced with zitazi successfully.'))
            ->catch(fn() => Log::error('Some sync zitazi jobs failed.'))
            ->name('Sync Zitazi variations')
            ->dispatch();
    }
}
