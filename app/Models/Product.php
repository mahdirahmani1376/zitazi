<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $own_id
 * @property string $source_id
 * @property string $source
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereOwnId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 *
 * @property int|null $price
 * @property int|null $stock
 * @property int|null $rial_price
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereRialPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStock($value)
 *
 * @property string|null $digikala_source
 * @property string|null $torob_source
 * @property-read \App\Models\ProductCompare|null $productCompare
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDigikalaSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereTorobSource($value)
 *
 * @property string|null $trendyol_source
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereTrendyolSource($value)
 *
 * @property int|null $min_price
 * @property string|null $category
 * @property string|null $brand
 * @property string|null $owner
 * @property string|null $product_name
 * @property string|null $decathlon_url
 * @property string|null $decathlon_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Variation> $variation
 * @property-read int|null $variation_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereBrand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDecathlonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDecathlonUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereMinPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereOwner($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereProductName($value)
 *
 * @property string|null $torob_id
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereTorobId($value)
 *
 * @mixin \Eloquent
 */
class Product extends Model
{
    protected $guarded = [

    ];

    public function belongsToTrendyol(): bool
    {
        return ! empty($this->trendyol_source);
    }

    public function belongsToIran(): bool
    {
        return ! empty($this->digikala_source) || ! empty($this->torob_source) && ! $this->belongsToDecalthon();
    }

    public function productCompare(): HasOne
    {
        return $this->hasOne(ProductCompare::class, 'product_id');
    }

    public function variation()
    {
        return $this->hasMany(Variation::class, 'product_id');
    }

    public function belongsToDecalthon()
    {
        return ! empty($this->decathlon_url);
    }

    public function decathlonVariation(): HasOne
    {
        return $this->hasOne(Variation::class, 'trendyol_product_id');
    }
}
