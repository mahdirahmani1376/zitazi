<?php

namespace App\Actions\Crawler;

use App\Models\Product;

interface ProductAbstractCrawler
{
    public function crawl(Product $product);

    public function supports(Product $product): bool;
}
