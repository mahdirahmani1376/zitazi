<?php

namespace App\Console\Commands;

use App\Actions\LogManager;
use App\Actions\SeedVariationsForDecathlonAction;
use App\Actions\SeedVariationsForTrendyolAction;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Variation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ListenForScrapeResponseCommand extends Command
{
    protected $signature = 'listen:scrape';

    protected $description = 'this command listens for scrape-response message';

    public function handle()
    {
        try {
            $this->listenForMessages();
        } catch (\Throwable $e) {
            $this->listenForMessages();
            $this->error($e->getMessage());
        }
    }

    private function logError($messageArray): void
    {
        $product = Product::find($messageArray['product_id']);
        foreach ($product->variations as $variation) {
            $oldStock = $variation->stock;
            $oldPrice = $variation->price;

            $variation->update([
                'status' => Variation::UNAVAILABLE,
                'stock' => 0,
            ]);

            if ($oldStock != $variation->stock) {
                $data = [
                    'old_stock' => $oldStock,
                    'new_stock' => $variation->stock,
                    'old_price' => $oldPrice,
                    'new_price' => $variation->rial_price,
                    'variation_own_id' => $variation->own_id,
                    'product_own_id' => $variation->product->own_id,
                ];

                SyncLog::create($data);
            }
        }
        LogManager::logProduct($product, 'sync-error', [
            'result' => $messageArray,
        ]);
    }

    private function listenForMessages()
    {
        $this->info('starting to listen');

        $message = Redis::blpop('scrape_result', 0);

        $messageArray = json_decode($message[1], true);
        $this->info('Message received: ' . json_encode($message));

        if (!$messageArray['success']) {
            $this->logError($messageArray);
        } else {
            $product = Product::findOrFail($messageArray['product_id']);
            if ($product->belongsToDecalthon()) {
                app(SeedVariationsForDecathlonAction::class)->execute($messageArray, $messageArray['sync'] ?? false);
            } else if ($product->belongsToTrendyol()) {
                app(SeedVariationsForTrendyolAction::class)->execute($messageArray, $messageArray['sync'] ?? false);
            }
        }
    }

}
