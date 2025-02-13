<?php

namespace App\Models;

use App\Enums\SourceEnum;
use Illuminate\Database\Eloquent\Model;

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
 * @mixin \Eloquent
 */
class Product extends Model
{
    protected $guarded = [

    ];

    protected $casts = [
        'source' => SourceEnum::class,
    ];

    public function belongsToTrendyol(): bool
    {
        return $this->source == SourceEnum::TRENDYOL;
    }
}
