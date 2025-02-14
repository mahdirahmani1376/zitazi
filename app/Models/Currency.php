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
        $timeUntilEndOfDay = now()->diffInMinutes(now()->endOfDay());

        return Cache::remember('try_rate',$timeUntilEndOfDay,function () {
            $response = Http::acceptJson()->withQueryParameters([
                'api_key' => env('NAVASAN_KEY')
            ])->get('http://api.navasan.tech/latest')->json();
    
            $rate = data_get($response,'try.value');
            
            if (empty($rate))
            {
                $rate = static::lastTryRate() ?? 2400;
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
