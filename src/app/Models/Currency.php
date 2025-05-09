<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Log;

/**
 * @property int $id
 * @property int $rate
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Currency whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Currency extends Model
{
    protected $guarded = [];

    public static function lastTryRate()
    {
        return self::orderByDesc('created_at')->where('name', 'try')->first()?->rate;
    }

    public static function syncTryRate()
    {
        $timeUntilEndOfDay = now()->diffInMinutes(now()->endOfDay());

        return Cache::remember('try_rate', $timeUntilEndOfDay, function () {
            try {
                $response = Http::acceptJson()->withQueryParameters([
                    'api_key' => env('NAVASAN_KEY'),
                ])->get('http://api.navasan.tech/latest')->json();

                $rate = data_get($response, 'try.value');

                if (empty($rate)) {
                    $rate = static::lastTryRate() ?? 2400;
                } else {
                    static::create([
                        'rate' => $rate,
                        'name' => 'try',
                    ]);
                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                $rate = static::lastTryRate() ?? 2400;
            }

            return $rate;
        });
    }

    public static function convertToRial($price, $profitRatio): int
    {
        if (empty($profitRatio)) {
            $profitRatio = 60;
        }

        $profitRatio = 1 + ($profitRatio / 100);


        $rialPrice = static::syncTryRate() * $profitRatio * $price;

        return floor($rialPrice / 10000) * 10000;
    }
}
