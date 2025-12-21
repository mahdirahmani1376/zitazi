<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $product_id
 * @property string|null $sku
 * @property int|null $price
 * @property string|null $url
 * @property int|null $stock
 * @property string|null $size
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Product|null $product
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation whereSku($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation whereStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation whereUrl($value)
 *
 * @property int|null $rial_price
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation whereRialPrice($value)
 *
 * @property int|null $own_id
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variation whereOwnId($value)
 *
 * @mixin \Eloquent
 */
class Variation extends Model
{
    public const AVAILABLE = 'available';
    public const UNAVAILABLE = 'unavailable';
    public const UNAVAILABLE_ON_ZITAZI = 'unavailable_on_zitazi';
    public const UNAVAILABLE_ON_SOURCE_SITE = 'unavailable_on_source_site';
    public const GENERAL_ERROR = 'general error';
    protected $guarded = [

    ];

    public const STATUSES = [
        self::AVAILABLE,
        self::UNAVAILABLE,
        self::UNAVAILABLE_ON_ZITAZI,
        self::UNAVAILABLE_ON_SOURCE_SITE,
        self::GENERAL_ERROR,
    ];

    public static function TableFilters(): array
    {
        return [
            self::AVAILABLE => self::AVAILABLE,
            self::UNAVAILABLE => self::UNAVAILABLE,
            self::UNAVAILABLE_ON_ZITAZI => self::UNAVAILABLE_ON_ZITAZI,
            self::UNAVAILABLE_ON_SOURCE_SITE => self::UNAVAILABLE_ON_SOURCE_SITE,
            self::GENERAL_ERROR => self::GENERAL_ERROR,
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function trendyolProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'trendyol_product_id');
    }

}
