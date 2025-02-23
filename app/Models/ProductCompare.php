<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $product_id
 * @property int $digikala_price
 * @property int $torob_price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereDigikalaPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereTorobPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductCompare whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class ProductCompare extends Model
{
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class,'product_id');
    }
}
