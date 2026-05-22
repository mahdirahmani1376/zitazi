<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogModel extends Model
{
    use MassPrunable;
    protected $casts = [
        'data' => 'json'
    ];

    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(3));
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
