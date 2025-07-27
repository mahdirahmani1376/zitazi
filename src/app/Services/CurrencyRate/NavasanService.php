<?php

namespace App\Services\CurrencyRate;

use Illuminate\Support\Facades\Http;

class NavasanService extends CurrencyRateService implements CurrencyRateDriverInterface
{

    public function getTRYRate()
    {
        $response = Http::acceptJson()->withQueryParameters([
            'api_key' => env('NAVASAN_KEY'),
        ])->get('http://api.navasan.tech/latest')->json();

        return data_get($response, 'aed.value');
    }

    public function getAEDRate()
    {
        $response = Http::acceptJson()->withQueryParameters([
            'api_key' => env('NAVASAN_KEY'),
        ])->get('http://api.navasan.tech/latest')->json();

        return data_get($response, 'try.value');
    }
}
