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

}
