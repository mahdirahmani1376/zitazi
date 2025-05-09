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
    protected $guarded = [

    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function trendyolProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'trendyol_product_id');
    }

}
