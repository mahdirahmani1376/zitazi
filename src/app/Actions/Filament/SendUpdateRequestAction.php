<?php

namespace App\Actions\Filament;

use App\DTO\ZitaziUpdateDTO;
use App\Jobs\SyncZitaziJob;
use App\Models\Variation;

class SendUpdateRequestAction
{
    public static function execute(Variation $variation): void
    {
        SyncZitaziJob::dispatch($variation, ZitaziUpdateDTO::createFromArray([
            'price' => $variation->rial_price,
            'stock' => $variation->stock,
        ]));
    }
}
