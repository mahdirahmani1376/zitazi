<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $product_id
 * @property int $digikala_price
 * @property int $torob_price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereDigikalaPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereTorobPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereUpdatedAt($value)
 *
 * @property-read \App\Models\Product|null $product
 * @property int|null $digikala_zitazi_price
 * @property int|null $digikala_min_price
 * @property int|null $torob_min_price
 * @property int|null $zitazi_torob_price
 * @property int|null $zitazi_torob_price_recommend
 * @property int|null $zitazi_digikala_price_recommend
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereDigikalaMinPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereDigikalaZitaziPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereTorobMinPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereZitaziDigikalaPriceRecommend($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereZitaziTorobPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereZitaziTorobPriceRecommend($value)
 *
 * @property float|null $zitazi_digi_ratio
 * @property float|null $zitazi_torob_ratio
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereZitaziDigiRatio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereZitaziTorobRatio($value)
 *
 * @mixin \Eloquent
 */
class ProductCompare extends Model
{
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
