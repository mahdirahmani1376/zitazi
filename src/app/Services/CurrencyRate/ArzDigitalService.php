<?php

namespace App\Services\CurrencyRate;

class ArzDigitalService extends CurrencyRateService implements CurrencyRateDriverInterface
{
    public const BASE_URL = 'https://lake.arzdigital.com/web/api/v1/pub/coins?type=fiat';

    public function getTRYRate()
    {
        $response = $this->httpService->sendWithCache('get', self::BASE_URL);

        return collect($response['data'])->keyBy('symbol')->get('TRY')['toman'];
    }

    public function getAEDRate()
    {
        $response = $this->httpService->sendWithCache('get', self::BASE_URL);

        return collect($response['data'])->keyBy('symbol')->get('AED')['toman'];
    }
}
