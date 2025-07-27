<?php

namespace App\Services\CurrencyRate;

use App\Actions\SendHttpRequestAction;

class CurrencyRateService
{
    public function __construct(
        public SendHttpRequestAction $sendHttpRequestAction
    )
    {
    }

    public static function getDriver()
    {
        return app()->make(config('currency-rate.driver') . 'Service');
    }
}
