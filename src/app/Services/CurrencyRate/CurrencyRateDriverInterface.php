<?php

namespace App\Services\CurrencyRate;

interface CurrencyRateDriverInterface
{
    public function getTRYRate();

    public function getAEDRate();
}
