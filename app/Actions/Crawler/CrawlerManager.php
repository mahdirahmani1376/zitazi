<?php

namespace App\Actions\Crawler;

use App\Models\Product;

class CrawlerManager
{
    public function __construct(
        protected array $crawlers = []
    )
    {
    }

    public function crawl(Product $product): void
    {
        foreach ($this->crawlers as $crawler) {
            if ($crawler->supports($product)) {
                $crawler->crawl($product);
            }
        }
    }
}
