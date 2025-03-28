<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $guarded = [

    ];

    public function belongsToTrendyol(): bool
    {
        return ! empty($this->trendyol_source);
    }

    public function belongsToElele(): bool
    {
        return ! empty($this->elele_source);
    }

    public function belongsToIran(): bool
    {
        return ! empty($this->digikala_source) || ! empty($this->torob_source) && ! $this->belongsToDecalthon();
    }

    public function productCompare(): HasOne
    {
        return $this->hasOne(ProductCompare::class, 'product_id');
    }

    public function variations()
    {
        return $this->hasMany(Variation::class, 'product_id');
    }

    public function defaultVariation(): ?Variation
    {
        return $this->variations()
        ->whereNot('stock','=',0)
        ->whereNotNull('price')
        ->first();
    }

    public function belongsToDecalthon()
    {
        return ! empty($this->decathlon_url);
    }

    public function decathlonVariation(): HasOne
    {
        return $this->hasOne(Variation::class, 'trendyol_product_id');
    }

    public function isForeign(): bool
    {
        return $this->belongsToTrendyol() || $this->belongsToElele();
    }

    public function onPromotion()
    {
        return $this->promotion;
    }

}
