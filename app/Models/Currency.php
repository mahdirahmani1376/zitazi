<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $guarded = [];

    public function lastRate()
    {
        return self::orderByDesc('created_at')->first()?->rate;
    }

    public function getLatestRate()
    {
        // $response = 
    }
}
