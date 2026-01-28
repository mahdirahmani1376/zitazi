<?php

namespace App\Console\Commands;

use App\DTO\ZitaziUpdateDTO;
use App\Models\Currency;
use App\Models\Product;
use Illuminate\Console\Command;

class BatchSyncZitaziProductsCommand extends Command
{
    protected $signature = 'batch:sync-zitazi-products';

    protected $description = 'Command description';

    public function handle(): void
    {
        $currency = Currency::syncTryRate();

        Product::all()->each(function (Product $product) use ($currency) {
            $url = "https://zitazi.com/wp-json/wc/v3/products/12810";

            $body = [];
            foreach ($product->variations as $variation) {

                $dto = ZitaziUpdateDTO::createFromArray([
                    'stock_quantity' => $variation->stock,
                    'price' => $variation->rial_price * $currency
                ]);

                $stockStatus = ZitaziUpdateDTO::OUT_OF_STOCK;
                if ($dto->stock_quantity > 0) {
                    $stockStatus = ZitaziUpdateDTO::IN_STOCK;
                }

                $data = $dto->getUpdateBody();

                $data['stock_status'] = $stockStatus;

                if (!empty($dto?->price)) {
                    $data['sale_price'] = null;
                    $data['regular_price'] = '' . $dto->price;
                }

                $data['id'] = $variation->id;

                $body[] = $data;
            }

//           $response = \Illuminate\Support\Facades\Http::withBasicAuth(
//               env('user'),
//               env('security_pass')
//           )
//            LogManager::logVariation($variation, 'variation_update', [
//                'body' => $data,
//                'response' => [
//                    'price' => data_get($response, 'price'),
//                    'sale_price' => data_get($response, 'sale_price'),
//                    'regular_price' => data_get($response, 'regular_price'),
//                    'stock_quantity' => data_get($response, 'stock_quantity'),
//                    'stock_status' => data_get($response, 'stock_status'),
//                    'zitazi_id' => data_get($response, 'id'),
//                ],
//            ]);
        });
    }
}
