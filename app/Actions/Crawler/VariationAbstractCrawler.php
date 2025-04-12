<?php

namespace App\Actions\Crawler;

use App\Models\Variation;

interface VariationAbstractCrawler
{
    public function crawl(Variation $variation);

    public function supports(Variation $variation): bool;
}
