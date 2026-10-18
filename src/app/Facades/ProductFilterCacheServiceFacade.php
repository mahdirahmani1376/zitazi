<?php

namespace App\Facades;

use App\Services\ProductFilterCacheService;
use App\Services\TorobService;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin  ProductFilterCacheService
 */
class ProductFilterCacheServiceFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ProductFilterCacheService::class;
    }
}
