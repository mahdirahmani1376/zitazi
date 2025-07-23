<?php

namespace App\Actions;

use App\Actions\Crawler\BaseVariationCrawler;
use App\Models\Currency;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Automattic\WooCommerce\Client;

class SyncVariationsActions
{
    public function execute(Variation $variation): void
    {
        BaseVariationCrawler::crawlVariation($variation);
    }
}
