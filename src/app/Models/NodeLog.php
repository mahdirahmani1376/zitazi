<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NodeLog extends Model
{
    protected $casts = [
        'data' => 'json',
    ];

    public function prunable()
    {
        return static::where('created_at', '<=', now()->subDays(2));
    }
}
