<?php

namespace App\Actions;

use App\Actions\Crawler\BaseCrawler;
use App\Models\Product;

class SyncProductsAction
{
    public function __construct(
        public BaseCrawler $baseProductCrawler
    )
    {
    }

    public function __invoke(Product $product): void
    {
        $this->baseProductCrawler->crawl($product);
    }
}
