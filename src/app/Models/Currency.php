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

    public static function lastEurRate()
    {
        return self::orderByDesc('created_at')->where('name', 'eur')->first()?->rate;
    }

    public static function syncTryRate()
    {
        $timeUntilEndOfDay = now()->diffInMinutes(now()->endOfDay());

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

    public static function syncEurRate()
    {
        $timeUntilEndOfDay = now()->diffInMinutes(now()->endOfDay());

        return Cache::remember('eur_rate', $timeUntilEndOfDay, function () {
            try {
                $rate = app()->make(CurrencyRateDriverInterface::class)->getEURRate();

                if (empty($rate)) {
                    $rate = static::lastEurRate() ?? 2400;
                } else {
                    static::create([
                        'rate' => $rate,
                        'name' => 'eur',
                    ]);
                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
                $rate = static::lastEurRate() ?? 2400;
            }

            return $rate;
        });
    }


    public static function convertToRial($price, $symbol = 'TRY'): int
    {
        if ($symbol === 'TRY') {
            $rialPrice = static::syncTryRate() * $price;
        } elseif ($symbol === 'EUR') {
            $rialPrice = static::syncEurRate() * $price;
        }

        return floor($rialPrice / 10000) * 10000;
    }

    public static function syncDirhamTryRate()
    {
        $timeUntilEndOfDay = now()->diffInMinutes(now()->endOfDay());

        return Cache::remember('aed_rate', $timeUntilEndOfDay, function () {
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
