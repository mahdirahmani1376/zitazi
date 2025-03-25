<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
 *
 * @property int $id
 * @property string|null $url
 * @property int|null $average
 * @property int|null $total
 * @property string|null $source
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereAverage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereUrl($value)
 * @property string|null $zitazi_category
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report whereZitaziCategory($value)
 * @mixin \Eloquent
 */
class Report extends Model
{
    protected $guarded = [];

    public function topDigikalaSubCategories(): string
    {
        $data =  SubCategory::query()
            ->selectRaw('name,count(name)')
            ->groupBy('name')
            ->whereHas('externalProduct',function ($q){
                $q
                    ->where('source','digikala')
                    ->where('category',$this->zitazi_category);
            })
            ->orderByRaw('count(name) desc')
            ->take(5)
            ->get();

        $totalCount = $data->sum('count(name)');

        $data = $data->map(function ($item) use ($totalCount) {
            $item['percentage'] = (int)(($item['count(name)'] / $totalCount) * 100);
            return $item['name'] . ': ' . $item['percentage'];
        });


        return implode(', ' . PHP_EOL ,$data->toArray());
    }

    public function topTorobSubCategories(): string
    {
        $data =  SubCategory::query()
            ->selectRaw('name,count(name)')
            ->groupBy('name')
            ->whereHas('externalProduct',function ($q){
                $q
                    ->where('source','torob')
                    ->where('category',$this->zitazi_category);
            })
            ->orderByRaw('count(name) desc')
            ->take(5)
            ->get();

        $totalCount = $data->sum('count(name)');

        $data = $data->map(function ($item) use ($totalCount) {
            $item['percentage'] = (int)(($item['count(name)'] / $totalCount) * 100);
            return $item['name'] . ': ' . $item['percentage'];
        });


        return implode(', ' . PHP_EOL ,$data->toArray());
    }
}
