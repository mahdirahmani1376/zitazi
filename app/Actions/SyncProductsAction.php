<?php

namespace App\Actions;

use App\Actions\Crawler\BaseProductCrawler;
use App\Models\Product;

class SyncProductsAction
{
    public function __invoke(Product $product): void
    {
        BaseProductCrawler::CrawlProduct($product);
    }
}
