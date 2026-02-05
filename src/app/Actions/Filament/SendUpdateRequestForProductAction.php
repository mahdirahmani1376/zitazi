<?php

namespace App\Actions\Filament;

use App\DTO\ZitaziUpdateDTO;
use App\Jobs\SyncZitaziJob;
use App\Models\Product;

class SendUpdateRequestForProductAction
{
    public static function execute(Product $product): void
    {
        foreach ($product->variations as $variation) {
            SyncZitaziJob::dispatchSync($variation, ZitaziUpdateDTO::createFromArray([
                'price' => $variation->rial_price,
                'stock_quantity' => $variation->stock,
            ]));
        }
    }
}
