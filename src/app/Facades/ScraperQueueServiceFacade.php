<?php

namespace App\Facades;

use App\Services\ScraperQueueService;
use Illuminate\Support\Facades\Facade;

/**
 * @see ScraperQueueService
 */
class ScraperQueueServiceFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ScraperQueueService::class;
    }
}
