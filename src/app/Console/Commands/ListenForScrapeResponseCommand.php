<?php

namespace App\Console\Commands;

use App\Actions\LogManager;
use App\Actions\SeedVariationsForDecathlonAction;
use App\Actions\SeedVariationsForTrendyolAction;
use App\DTO\ZitaziUpdateDTO;
use App\Jobs\SyncZitaziJob;
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
        while (true) {
            try {
                $this->info('starting to listen for messages');
                $this->listenForMessages();
            } catch (\Throwable $e) {
                $this->error('error happened: ' . $e->getMessage());
            }
        }
    }

    private function proccessTrendyolError($messageArray): void
    {
        $product = Product::find($messageArray['product_id']);

        //todo Attempted to use detached Frame '83E1F739C7D81B6698FDD82AAB3603E9'. Error response.error.message
        if (data_get($messageArray, 'response.response_data.statusCode') === 410 || !empty(data_get('response.error.message', null))) {
            $this->unavailableAllVariationsAndLog($product, $messageArray);
        }

    }

    private function proccessDecathlonError($messageArray): void
    {
        $product = Product::find($messageArray['product_id']);
        if (in_array(data_get($messageArray, 'response.response_status'), [403, 429]) && data_get($messageArray, 'source') === 'Decathlon') {
            Redis::rpush(
                config('queue.DE_QUEUE_IN'),
                json_encode([
                    'product' => $product->only([
                        'decathlon_url',
                        'id'
                    ]),
                    'bulk' => true,
                ])
            );

            LogManager::logProduct($product, 'product pushed back to queue', []);
        }

        if (data_get($messageArray, 'response.error.message') === 'offers is not iterable') {
            $this->unavailableAllVariationsAndLog($product, $messageArray);
        }
    }


    private function listenForMessages(): void
    {
        $this->info('starting to listen');

        $message = Redis::blpop('scrape_result', 0);

        $messageArray = json_decode($message[1], true);
        $this->info('Message received for product_id: ' . data_get($messageArray, 'product_id'));

        if (!$messageArray['success']) {
            if ($messageArray['source'] === 'Trendyol') {
                $this->proccessTrendyolError($messageArray);
            } elseif ($messageArray['source'] === 'Decathlon') {
                $this->proccessDecathlonError($messageArray);
            }
        } else {
            $product = Product::findOrFail($messageArray['product_id']);
            if ($product->belongsToDecalthon()) {
                app(SeedVariationsForDecathlonAction::class)->execute($messageArray, $messageArray['bulk'] ?? false);
            } else if ($product->belongsToTrendyol()) {
                app(SeedVariationsForTrendyolAction::class)->execute($messageArray, $messageArray['bulk'] ?? false);
            }
        }
    }

    private function unavailableAllVariationsAndLog(Product $product, $messageArray): void
    {
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


            $updateData = ZitaziUpdateDTO::createFromArray([
                'stock_quantity' => 0,
                'price' => $variation->rial_price
            ]);

            $variation->update([
                'status' => Variation::UNAVAILABLE_ON_SOURCE_SITE,
                'stock' => 0,
            ]);

            $queue = data_get($messageArray, 'response.bulk') ? 'bulk-sync-products' : 'sync-products';

            SyncZitaziJob::dispatch($variation, $updateData)->onQueue($queue);
        }

        LogManager::logProduct($product, 'sync-error', [
            'result' => $messageArray,
        ]);
    }

}
