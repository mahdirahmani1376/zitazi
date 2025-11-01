<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NodeLog extends Model
{
    protected $casts = [
        'data' => 'json',
    ];
}
