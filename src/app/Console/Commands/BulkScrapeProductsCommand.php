<?php

namespace App\Console\Commands;

use App\Actions\LogManager;
use App\Enums\SyncStatusEnum;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class BulkScrapeProductsCommand extends Command
{
    protected $signature = 'app:bulk-scrape';

    protected $description = 'sends all products to sync message queue';

    public int $total = 0;
    public int $enqueued = 0;
    public int $invalid = 0;

    public function handle(): void
    {
        Product::query()
            ->chunk(100, function (Collection $items) {
                Redis::pipeline(function ($pipe) use ($items) {
                    /** @var Product $product */
                    foreach ($items as $product) {
                        $this->total++;

                        LogManager::logProduct($product, 'product enqueued for batch processing');

                        if ($product->belongsToTorob()) {
                            $fullUrl = $product->getTorobFullUrl();
                            if (empty($fullUrl)) {
                                LogManager::logProduct($product, 'torob full url can not be set');
                                $this->invalid++;
                            } else {
                                $product->setSyncStatus(SyncStatusEnum::ENQUEUED, false);
                                LogManager::logProduct($product, 'product enqueued for batch processing');
                                $pipe->rpush(
                                    config('queue.TOROB_QUEUE_IN'),
                                    json_encode([
                                        'product' => $product->getTorobQueueScrapeData(),
                                        'bulk' => true,
                                    ])
                                );
                                $this->enqueued++;
                            }
                        } else if ($product->belongsToDecalthon()) {

                            $product->setSyncStatus(SyncStatusEnum::ENQUEUED, false);
                            LogManager::logProduct($product, 'product enqueued for batch processing');
                            $pipe->rpush(
                                config('queue.DE_QUEUE_IN'),
                                json_encode([
                                    'product' => $product->only([
                                        'decathlon_url',
                                        'id'
                                    ]),
                                    'bulk' => true,
                                ])
                            );
                            $this->enqueued++;

                        } else if ($product->belongsToTrendyol()) {
                            $fullUrl = $product->getTrendyolFullUrl();
                            if (empty($fullUrl)) {
                                LogManager::logProduct($product, 'full url can not be set');
                                $this->invalid++;
                            } else {
                                $product->setSyncStatus(SyncStatusEnum::ENQUEUED, false);
                                LogManager::logProduct($product, 'product enqueued for batch processing');
                                $pipe->rpush(
                                    config('queue.TR_QUEUE_IN'),
                                    json_encode([
                                        'product' => $product->getTrendyolQueueScrapeData(),
                                        'bulk' => true,
                                    ])
                                );
                                $this->enqueued++;
                            }
                        }

                    }

                });
            });

        Log::info('Bulk scrape chunk completed', [
            'total' => $this->total,
            'enqueued' => $this->enqueued,
            'invalid' => $this->invalid,
        ]);

        $this->info('bulk scrape result');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total processed', $this->total],
                ['Enqueued', $this->enqueued],
                ['Invalid URL', $this->invalid],
            ]
        );
    }

    /**
     * @return void
     */
    public function bulkSyncTrendyol(): void
    {
        Product::query()
            ->whereNotNull('trendyol_source')
            ->whereRaw("TRIM(trendyol_source) != ''")
            ->chunk(100, function (Collection $items) {
                Redis::pipeline(function ($pipe) use ($items) {
                    /** @var Product $item */
                    foreach ($items as $product) {
                        $this->total++;


                    }

                });
            });

    }

    /**
     * @return void
     */
    public function bulkSyncDecathlon(): void
    {
        Product::query()
            ->chunk(100, function (Collection $items) {
                Redis::pipeline(function ($pipe) use ($items) {
                    /** @var Product $item */
                    foreach ($items as $product) {
                        $this->total++;
                        $product->setSyncStatus(SyncStatusEnum::ENQUEUED, false);
                        LogManager::logProduct($product, 'product enqueued for batch processing');
                        $pipe->rpush(
                            config('queue.DE_QUEUE_IN'),
                            json_encode([
                                'product' => $product->only([
                                    'decathlon_url',
                                    'id'
                                ]),
                                'bulk' => true,
                            ])
                        );
                        $this->enqueued++;

                    }

                });
            });

    }

    private function bulkSyncAll(): void
    {
        $this->bulkSyncDecathlon();
        $this->bulkSyncTrendyol();
    }
}
