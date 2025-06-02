<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    public function product(): ?Product
    {
        return Product::firstWhere('own_id', $this->product_own_id);
    }

    public function variation()
    {
        return Variation::firstWhere('own_id', $this->variation_own_id);
    }
}
