<?php

namespace App\Models;

use App\Enums\SourceEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 
 *
 * @property int $id
 * @property string $own_id
 * @property string $source_id
 * @property string $source
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereOwnId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 * @property int|null $price
 * @property int|null $stock
 * @property int|null $rial_price
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereRialPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStock($value)
 * @property string|null $digikala_source
 * @property string|null $torob_source
 * @property-read \App\Models\ProductCompare|null $productCompare
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDigikalaSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereTorobSource($value)
 * @mixin \Eloquent
 */
class Product extends Model
{
    protected $guarded = [

    ];

    public function belongsToTrendyol(): bool
    {
        return !empty($this->trendyol_source);
    }

    public function belongsToIran(): bool
    {
        return !empty($this->digikala_source) || !empty($this->torob_source);
    }

    public function productCompare(): HasOne
    {
        return $this->hasOne(ProductCompare::class,'product_id');
    }
}
