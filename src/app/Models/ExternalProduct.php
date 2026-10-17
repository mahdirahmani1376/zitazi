<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $source
 * @property string|null $source_id
 * @property string|null $category
 * @property int|null $price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalProduct query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalProduct whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalProduct whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalProduct whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalProduct wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalProduct whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalProduct whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalProduct whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalProduct whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ExternalProduct extends Model
{
    protected $guarded = [

    ];
}
