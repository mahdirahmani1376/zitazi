<?php

namespace App\Facades;

use App\Services\TorobService;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin  TorobService
 */
class TorobServiceFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TorobService::class;
    }
}
