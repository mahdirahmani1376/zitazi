<?php

namespace App\Console\Commands;

use App\DTO\ZitaziUpdateDTO;
use App\Jobs\BatchSyncZitaziJob;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class BatchSyncZitaziProductsCommand extends Command
{
    protected $signature = 'batch:sync-zitazi-products';

    protected $description = 'Command description';

    public function handle(): void
    {
        $variationIds = Variation::query()
            ->where('base_source', Product::ZITAZI)
            ->where('item_type', Product::VARIATION_UPDATE)
            ->get()
            ->pluck('product_id')
            ->unique();

        $jobs = [];
        Product::query()
            ->whereNot('promotion', 1)
            ->whereIn('id', $variationIds->toArray())
            ->each(function (Product $product) {
                $body = [];
                foreach ($product->variations as $variation) {
                    if ($variation->status == Variation::AVAILABLE) {
                        $stock = $variation->stock;
                        $price = $variation->rial_price;

                        if ($stock == 0) {
                            $otherSource = null;
                            if ($variation->source === Product::SOURCE_TRENDYOL) {
                                $otherSource = Product::SOURCE_DECATHLON;
                            } elseif ($variation->source === Product::SOURCE_DECATHLON) {
                                $otherSource = Product::SOURCE_TRENDYOL;
                            }

                            if (!empty($otherSource)) {
                                if ($variation->item_type === Product::VARIATION_UPDATE) {
                                    $otherSellerVariant =
                                        Variation::query()
                                            ->where('own_id', $variation->own_id)
                                            ->whereNot('id', $variation->id)
                                            ->where('source', $otherSource)
                                            ->where('item_type', Product::VARIATION_UPDATE)
                                            ->first();
                                } else {
                                    $otherSellerVariant =
                                        Variation::query()
                                            ->where('product_id', $variation->product_id)
                                            ->whereNot('id', $variation->id)
                                            ->where('source', $otherSource)
                                            ->where('item_type', Product::VARIATION_UPDATE)
                                            ->first();
                                }


                                if (!empty($otherSellerVariant)) {
                                    $stock = $otherSellerVariant->stock;
                                    $price = $otherSellerVariant->rial_price;
                                }
                            }

                        }

                        $updateData = ZitaziUpdateDTO::createFromArray([
                            'stock_quantity' => $stock,
                            'price' => $price,
                        ]);
                    } else {
                        $updateData = ZitaziUpdateDTO::createFromArray([
                            'stock_quantity' => 0,
                        ]);
                    }

                    $stockStatus = ZitaziUpdateDTO::OUT_OF_STOCK;
                    if ($updateData->stock_quantity > 0) {
                        $stockStatus = ZitaziUpdateDTO::IN_STOCK;
                    }

                    $data = $updateData->getUpdateBody();

                    $data['stock_status'] = $stockStatus;

                    if (!empty($dto?->price)) {
                        $data['sale_price'] = null;
                        $data['regular_price'] = '' . $dto->price;
                    }

                    $data['id'] = $variation->own_id;
                    $body[] = $data;
                }

                $body = [
                    'update' => $body
                ];

                $jobs[] = new BatchSyncZitaziJob($product, $body);

            });

        Bus::batch($jobs)
            ->then(fn() => Log::info('All batch varations synced with zitazi successfully.'))
            ->catch(fn() => Log::error('Some sync varations jobs failed.'))
            ->name('Sync varations variations')
            ->dispatch();
    }
}
