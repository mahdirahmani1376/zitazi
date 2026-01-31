<?php

namespace App\Jobs;

use App\DTO\ZitaziUpdateDTO;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BatchSyncZitaziJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public                 $variationData,
        public ZitaziUpdateDTO $zitaziUpdateDTO
    )
    {
    }

    public function handle(): void
    {

    }
}
