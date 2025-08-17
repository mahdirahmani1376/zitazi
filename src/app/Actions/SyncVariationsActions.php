<?php

namespace App\Actions;

use App\Actions\Crawler\BaseVariationCrawler;
use App\Models\Variation;

class SyncVariationsActions
{
    public function execute(Variation $variation): void
    {
            BaseVariationCrawler::crawlVariation($variation);
    }
}
