<?php

namespace App\Actions;

use App\Actions\Crawler\BaseProductCrawler;
use App\Models\Product;

class SyncProductsAction
{
    public function __construct(
        public BaseProductCrawler $baseProductCrawler
    )
    {
    }

    public function __invoke(Product $product): void
    {
        $this->baseProductCrawler->crawlProduct($product);
    }
}
