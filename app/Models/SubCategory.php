<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubCategory extends Model
{
    public function externalProduct(): BelongsTo
    {
        return $this->belongsTo(ExternalProduct::class,'external_product_id');
    }
}
