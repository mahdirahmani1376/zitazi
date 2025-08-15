<?php

namespace App\Services\CurrencyRate;

use App\Actions\HttpService;

class CurrencyRateService
{
    public function __construct(
        public HttpService $httpService
    )
    {
    }

}
