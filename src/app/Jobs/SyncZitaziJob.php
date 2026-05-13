<?php

namespace App\Jobs;

use App\Actions\Crawler\BaseVariationCrawler;
use App\DTO\ZitaziUpdateDTO;
use App\Models\Variation;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncZitaziJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(
        public Variation       $variation,
        public ZitaziUpdateDTO $zitaziUpdateDTO
    )
    {
    }

    public function handle(BaseVariationCrawler $baseVariationCrawler): void
    {
        $result = $baseVariationCrawler->syncZitazi($this->variation, $this->zitaziUpdateDTO);

        if (!$result) {
            $this->release(300);
        }
    }

    public function tries(): int
    {
        return 2;
    }
}
