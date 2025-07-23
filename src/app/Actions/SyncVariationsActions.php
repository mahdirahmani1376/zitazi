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
        try {
            BaseVariationCrawler::crawlVariation($variation);
        } catch (\Exception $e) {
            \Log::error('sync-variations-general-error',[
                'variation_id' => $variation->id,
                'error' => $e->getMessage(),
            ]);
            dump($e->getMessage(),$variation->id);
        }
    }
}
