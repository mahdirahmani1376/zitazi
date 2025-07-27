<?php

namespace App\Models;

use App\Services\CurrencyRate\CurrencyRateDriverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property int $rate
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
        $rate = app()->make(CurrencyRateDriverInterface::class)->getTRYRate();
        dd($rate);
        $timeUntilEndOfDay = now()->diffInMinutes(now()->endOfDay());

        //todo change this
        return Cache::remember('try_rate', $timeUntilEndOfDay, function () {
            try {
                $rate = app()->make(CurrencyRateDriverInterface::class)->getTRYRate();

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

    public static function convertToRial($price): int
    {
        $rialPrice = static::syncTryRate() * $price;

        return floor($rialPrice / 10000) * 10000;
    }

    public static function syncDirhamTryRate()
    {
        $timeUntilEndOfDay = now()->diffInMinutes(now()->endOfDay());

        return Cache::remember('try_rate', $timeUntilEndOfDay, function () {
            try {
                $rate = app()->make(CurrencyRateDriverInterface::class)->getAEDRate();

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

    public static function convertDirhamToRial($price): int
    {
        $rialPrice = static::syncDirhamTryRate() * $price;

        return floor($rialPrice / 10000) * 10000;
    }

}
