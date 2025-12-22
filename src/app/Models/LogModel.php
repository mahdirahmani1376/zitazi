<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogModel extends Model
{
    protected $casts = [
        'data' => 'json'
    ];

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
