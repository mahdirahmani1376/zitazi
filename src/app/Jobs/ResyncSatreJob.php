<?php

namespace App\Jobs;

use App\Actions\LogManager;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResyncSatreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
    }

    public function handle(): void
    {
        app(\Database\Seeders\ProductSeeder::class)->seedSatreProducts();
        foreach (Product::where('base_source', Product::SATRE)->get() as $product) {
            try {
                SeedVariationsForProductJob::dispatchSync($product);
                foreach ($product->variations as $variation) {
                    try {
                        $updateData = ZitaziUpdateDTO::createFromArray([
                            'stock_quantity' => $variation->stock,
                            'price' => $variation->rial_price
                        ]);
                        SyncZitaziJob::dispatch($variation, $updateData);
                    } catch (\Throwable $th) {
                        LogManager::LogVariation($variation, 'error in satreh', [
                            'product' => $product->id,
                            'variation' => $variation->id,
                            'error' => $th->getMessage()
                        ]);
                    }
                }
            } catch (\Throwable $th) {
                LogManager::LogProduct($product, 'error in satreh', [
                    'product' => $product->id,
                    'own_id' => $product->own_id,
                    'error' => $th->getMessage()
                ]);
            }

        }
    }
}
