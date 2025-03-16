<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $name
 * @property int|null $price
 * @property string|null $web_client_absolute_url
 * @property string|null $random_key
 * @property string|null $name1
 * @property string|null $stock_status
 * @property string|null $more_info_url
 * @property int|null $rank
 * @property int|null $clickable
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct whereClickable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct whereMoreInfoUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct whereName1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct whereRandomKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct whereRank($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct whereStockStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TorobProduct whereWebClientAbsoluteUrl($value)
 * @mixin \Eloquent
 */
class TorobProduct extends Model
{
    protected $guarded = [];

    public function product(): Product
    {
        return Product::firstWhere('torob_id',$this->random_key);
    }
}
