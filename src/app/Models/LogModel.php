<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogModel extends Model
{
    use Prunable;
    protected $casts = [
        'data' => 'json'
    ];

    public function prunable()
    {
        return static::where('created_at', '<=', now()->subDay());
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
