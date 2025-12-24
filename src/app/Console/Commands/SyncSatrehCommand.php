<?php

namespace App\Console\Commands;

use App\DTO\ZitaziUpdateDTO;
use App\Jobs\SyncZitaziJob;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class SyncSatrehCommand extends Command
{
    protected $signature = 'app:sync-satreh';

    protected $description = 'sync all satreh variations';

    public function handle(): void
    {
        $variations = Variation::query()
            ->where('base_source', Product::SATRE)
            ->get();

        $jobs = [];
        foreach ($variations as $variation) {
            if ($variation->status == Variation::AVAILABLE) {
                $stock = $variation->stock;
                $price = $variation->rial_price;
                $updateData = ZitaziUpdateDTO::createFromArray([
                    'stock_quantity' => $stock,
                    'price' => $price,
                ]);
            } else {
                $updateData = ZitaziUpdateDTO::createFromArray([
                    'stock_quantity' => 0,
                ]);
            }

            $jobs[] = new SyncZitaziJob($variation, $updateData);
        }

        Bus::batch($jobs)
            ->then(fn() => Log::info('All variations synced with satreh successfully.'))
            ->catch(fn() => Log::error('Some sync satreh jobs failed.'))
            ->name('Sync satreh variations')
            ->dispatch();
    }
}
