<?php

namespace App\Console\Commands;

use App\DTO\ZitaziUpdateDTO;
use App\Models\Product;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmergencyProductUpdateCommand extends Command
{
    protected $signature = 'emergency:product-update {--source=} {--invalid} {--id=*}';

    protected $description = 'source options:[tr,de]';

    public function handle(): void
    {
        $this->emergencySyncVariationsWithOwnId();
        $this->emergencySyncVariationsWithoutOwnId();

        Notification::make()
            ->title('آپدیت اضطراری انجام شد')
            ->success()
            ->sendToDatabase(User::find(1));
    }

    private function emergencySyncVariationsWithOwnId(): void
    {
        $productsForBatchVariationsUpdate = DB::table('products as p')
            ->join('variations as v', 'p.id', '=', 'v.product_id')
            ->whereNotNull('v.own_id')
            ->where('p.promotion', '=', false)
            ->where('v.item_type', '=', Product::VARIATION_UPDATE)
            ->when($this->option('id'), function (Builder $query) {
                $query
                    ->whereIn('p.id', $this->option('id'));
            })
            ->when($this->option('source') === 'tr', function (Builder $query) {
                $query
                    ->whereNotNull('trendyol_source')
                    ->whereRaw('trim(trendyol_source) != ""');
            })
            ->when($this->option('source') === 'de', function (Builder $query) {
                $query
                    ->whereNotNull('p.decathlon_url')
                    ->whereRaw("TRIM(p.decathlon_url) != ''");
            })
            ->selectRaw("
                p.own_id as product_own_id,
                p.base_source as base_source,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                            'variation_own_id', v.own_id,
                            'rial_price', v.rial_price,
                            'stock', v.stock
                    )
                ) as variations
                ")
            ->groupByRaw('p.own_id')
            ->orderBy('product_own_id')
            ->havingRaw('count(*) > 2');

        $this->beginSendingRequestsForVariations($productsForBatchVariationsUpdate);
    }

    private function emergencySyncVariationsWithoutOwnId(): void
    {
        $productsForBatchProductUpdate = DB::table('products as p')
            ->join('variations as v', 'p.id', '=', 'v.product_id')
            ->whereNotNull('v.own_id')
            ->where('p.promotion', '=', false)
            ->where('v.item_type', '=', Product::PRODUCT_UPDATE)
            ->when($this->option('id'), function (Builder $query) {
                $query
                    ->whereIn('p.id', $this->option('id'));
            })
            ->when($this->option('source') === 'tr', function (Builder $query) {
                $query
                    ->whereNotNull('trendyol_source')
                    ->whereRaw('trim(trendyol_source) != ""');
            })
            ->when($this->option('source') === 'de', function (Builder $query) {
                $query
                    ->whereNotNull('p.decathlon_url')
                    ->whereRaw("TRIM(p.decathlon_url) != ''");
            })
            ->selectRaw("
                p.own_id as product_own_id,
                p.base_source as base_source,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                            'variation_own_id', v.own_id,
                            'rial_price', v.rial_price,
                            'stock', v.stock
                    )
                ) as variations
                ")
            ->groupByRaw('p.own_id')
            ->orderBy('product_own_id')
            ->havingRaw('count(*) = 1');

        $this->beginSendingRequestsForProducts($productsForBatchProductUpdate);
    }

    private function beginSendingRequestsForVariations(Builder $productQuery): void
    {
        $bar = $this->output->createProgressBar($productQuery->count());

        $productQuery->chunk(16, function (Collection $chunk) use ($bar) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($chunk, $bar) {
                    return $chunk->map(function ($product) use ($pool, $bar) {
                        $baseURl = "https://zitazi.com";
                        if ($product->base_source === Product::ZITAZI) {
                            $securityKey = config('services.zitazi.security_key');
                            $securityPass = config('services.zitazi.security_pass');
                        } else if ($product->base_source === Product::SATRE) {
                            $securityKey = config('services.satreh.security_key');
                            $securityPass = config('services.satreh.security_key');
                            $baseURl = 'https://proxy.mahdi-rahmani.ir/satreh';
                        }

                        $fullUrl = "{$baseURl}/wp-json/wc/v3/products/{$product->product_own_id}/variations/batch";
                        $body = [];

                        foreach (json_decode($product->variations, true) as $variation) {
                            $body[] = $this->getZitaziDto(id: $variation['variation_own_id'], price: $variation['rial_price'], stock: $variation['stock']);
                        }

                        $pool->withBasicAuth($securityKey, $securityPass)->post($fullUrl, [
                            "update" => $body
                        ]);

                        $bar->advance();

                        return $product;
                    });
                });

                collect($responses)->each(function (\Illuminate\Http\Client\Response $response) {
                    $this->info($response->status());
                });

            } catch (\Exception $e) {
                Log::error('error-in-emergency update', [
                    'error' => $e->getMessage()
                ]);
            }
        });

        $bar->finish();
    }

    private function beginSendingRequestsForProducts(Builder $productQuery): void
    {
        $bar = $this->output->createProgressBar($productQuery->count());

        $productQuery->chunk(16, function (Collection $chunk) use ($bar) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($chunk, $bar) {
                    return $chunk->map(function ($product) use ($pool, $bar) {
                        $baseURl = "https://zitazi.com";
                        if ($product->base_source === Product::ZITAZI) {
                            $securityKey = config('services.zitazi.security_key');
                            $securityPass = config('services.zitazi.security_pass');
                        } else if ($product->base_source === Product::SATRE) {
                            $securityKey = config('services.satreh.security_key');
                            $securityPass = config('services.satreh.security_key');
                            $baseURl = 'https://proxy.mahdi-rahmani.ir/satreh';
                        }

                        $fullUrl = "{$baseURl}/wp-json/wc/v3/products/batch";
                        $body = [];

                        foreach (json_decode($product->variations, true) as $variation) {
                            $body[] = $this->getZitaziDto(id: $product->product_own_id, price: $variation['rial_price'], stock: $variation['stock']);
                        }

                        $fullBody = [
                            "update" => $body
                        ];

                        $pool->withBasicAuth($securityKey, $securityPass)->post($fullUrl, $fullBody);

                        $bar->advance();

                        return $product;
                    });
                });

                collect($responses)->each(function (\Illuminate\Http\Client\Response $response) {
                    $this->info($response->status());
                });

            } catch (\Exception $e) {
                $this->error($e->getMessage());
                Log::error('error-in-emergency update', [
                    'error' => $e->getMessage()
                ]);
            }
        });

        $bar->finish();
    }

    private function getZitaziDto($id = null, $price = null, $stock = null): array
    {
        if ($this->option('invalid')) {
            return [
                "id" => $id,
                'stock_status' => ZitaziUpdateDTO::OUT_OF_STOCK,
                'stock_quantity' => 0,
            ];
        }

        return [
            "id" => $id,
            ...ZitaziUpdateDTO::getFullPayloadFromPriceAndStock($price, $stock)
        ];
    }

}
