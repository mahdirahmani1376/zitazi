<?php

namespace App\Actions;

use App\Actions\Crawler\CrawlerManager;
use App\Models\Product;

class SyncProductsAction
{
    public function __construct(
        public CrawlerManager $crawlerManager
    )
    {
    }

    public function execute(Product $product): void
    {
        $this->crawlerManager->crawl($product);
    }
}
