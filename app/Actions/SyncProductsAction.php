<?php

namespace App\Actions;

use App\Actions\Crawler\CrawlerManager;
use App\Models\Product;

class SyncProductsAction
{
    public function __construct(
        public CrawlerManager $baseProductCrawler
    )
    {
    }

    public function __invoke(Product $product): void
    {
        $this->baseProductCrawler->crawl($product);
    }
}
