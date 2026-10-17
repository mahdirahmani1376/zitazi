<?php

namespace App\Facades;

use App\Services\WoocommerceService;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin  WoocommerceService
 */
class WoocommerceServiceFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WoocommerceService::class;
    }
}
