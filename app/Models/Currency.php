<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Currency extends Model
{
    protected $guarded = [];

    public static function lastTryRate()
    {
        return self::orderByDesc('created_at')->where('name','try')->first()?->rate;
    }

    public static function syncTryRate()
    {
        return Cache::remember('try_rate',60 * 60 * 24,function () {
            $response = Http::acceptJson()->withQueryParameters([
                'api_key' => env('NAVASAN_KEY')
            ])->get('http://api.navasan.tech/latest')->json();
    
            $rate = data_get($response,'try.value');
            
            if (empty($rate))
            {
                $rate = static::lastTryRate();
            } else {
                static::create([
                    'rate' => $rate,
                    'name' => 'try'
                ]);
            }

            return $rate;
        });
    }
}
