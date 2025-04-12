<?php

namespace App\Actions;

use App\Actions\Crawler\BaseVariationCrawler;
use App\Models\Currency;
use App\Models\Variation;
use App\Services\WoocommerceService;
use Automattic\WooCommerce\Client;

class SyncVariationsActions
{
    private Client $woocommerce;

    private mixed $rate;

    public function __construct(
        public SendHttpRequestAction $sendHttpRequestAction
    ) {
        $this->rate = Currency::syncTryRate();
        $this->woocommerce = WoocommerceService::getClient();
    }

    public function __invoke(Variation $variation, bool $sync = true): void
    {
        $this->updateVariation($variation);

    }

    public function updateVariation(Variation $variation): void
    {
        BaseVariationCrawler::crawlVariation($variation);
    }
}
