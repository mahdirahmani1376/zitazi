<?php

namespace App\Console\Commands;

use App\Actions\LogManager;
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
            ->whereNull('v.own_id')
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
                    $result = $chunk->map(function ($product) use ($pool, $bar) {
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

                        $fullBody = [
                            "update" => $body
                        ];

                        return $pool->as($product->product_own_id)->withBasicAuth($securityKey, $securityPass)->post($fullUrl, $fullBody);
                    });

                    $bar->advance();

                    return $result;
                });

                foreach ($responses as $productOwnId => $response) {
                    $jsonResponse = $response->json();
                    $responseStatus = $response->status();

                    $product = Product::firstWhere('own_id', $productOwnId);

                    if (!$product) {
                        continue;
                    }

                    LogManager::logProduct($product, 'emergency update response', [
                        'response' => $jsonResponse['update'],
                        'response_status' => $responseStatus,
                    ]);
                }

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
                    $result = $chunk->map(function ($product) use ($pool, $bar) {
                        $baseURl = "https://zitazi.com";
                        if ($product->base_source === Product::ZITAZI) {
                            $securityKey = config('services.zitazi.security_key');
                            $securityPass = config('services.zitazi.security_pass');
                        } else if ($product->base_source === Product::SATRE) {
                            $securityKey = config('services.satreh.security_key');
                            $securityPass = config('services.satreh.security_key');
                            $baseURl = 'https://proxy.mahdi-rahmani.ir/satreh';
                        }

                        $fullUrl = "{$baseURl}/wp-json/wc/v3/products/$product->product_own_id";
                        $variation = json_decode($product->variations, true)[0];
                        $body = $this->getZitaziDto(id: $product->product_own_id, price: $variation['rial_price'], stock: $variation['stock'], idRequired: false);

                        return $pool->as($product->product_own_id)->withBasicAuth($securityKey, $securityPass)->post($fullUrl, $body);

                    });
                    $bar->advance();
                    return $result;
                });

                foreach ($responses as $productOwnId => $response) {
                    $jsonResponse = $response->json();
                    $responseStatus = $response->status();

                    $product = Product::firstWhere('own_id', $productOwnId);

                    if (!$product) {
                        continue;
                    }

                    LogManager::logProduct($product, 'emergency update response', [
                        'response' => $jsonResponse,
                        'response_status' => $responseStatus,
                    ]);
                }

            } catch (\Exception $e) {
                $this->error($e->getMessage());
                Log::error('error-in-emergency update', [
                    'error' => $e->getMessage()
                ]);
            }
        });

        $bar->finish();
    }

    private function getZitaziDto($id = null, $price = null, $stock = null, bool $idRequired = true): array
    {
        if ($this->option('invalid')) {
            $result = [
                'stock_status' => ZitaziUpdateDTO::OUT_OF_STOCK,
                'stock_quantity' => 0,
            ];
        } else {
            $result = [
                "id" => $id,
                ...ZitaziUpdateDTO::getFullPayloadFromPriceAndStock(stock: $stock, price: $price)
            ];
        }

        if ($idRequired) {
            $result['id'] = $id;
        }

        return $result;
    }

}
