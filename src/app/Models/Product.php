<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    public const TOROB_LOCK_FOR_UPDATE = 'torob_lock_for_update';
    public const SOURCE_TRENDYOL = 'trendyol';
    public const SOURCE_DECATHLON = 'decathlon';
    public const SOURCE_AMAZON = 'amazon';
    public const PRODUCT_UPDATE = 'product';
    public const VARIATION_UPDATE = 'variation';


    public function belongsToTrendyol(): bool
    {
        return !empty($this->trendyol_source);
    }

    public function belongsToElele(): bool
    {
        return !empty($this->elele_source);
    }

    public function belongsToIran(): bool
    {
        return !empty($this->digikala_source) || !empty($this->torob_source) && !$this->belongsToDecalthon();
    }

    public function belongsToTorob()
    {
        return !empty($this->torob_source);
    }

    public function belongsToDigikala(): bool
    {
        return !empty($this->digikala_source);
    }

    public function onlyHasTorobSource(): bool
    {
        return !empty($this->torob_source) && empty($this->trendyol_source);
    }


    public function belongsToTrendyolAndTorob(): bool
    {
        return !empty($this->torob_source) && !empty($this->trendyol_source);
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
            ->whereNot('stock', '=', 0)
            ->whereNotNull('price')
            ->first();
    }

    public function belongsToDecalthon()
    {
        return !empty($this->decathlon_url);
    }

    public function belongsToAmazon()
    {
        return !empty($this->amazon_source);
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
        return $this->promotion == 1;
    }

    public function getRatio()
    {
        $ratio = 1.6;
        if (!empty($this->markup)) {
            $ratio = 1 + ($this->markup / 100);
        }

        return $ratio;
    }

    public function getTrendyolContentId(): ?string
    {
        $contentId = null;
        if (!empty($this->trendyol_source)) {
            if (preg_match('/p-(\d+)/', $this->trendyol_source, $m)) {
                $contentId = $m[1];
            }
        }
        return $contentId;
    }

    public function getTrendyolMerchantId(): ?string
    {
        $contentId = null;
        if (!empty($this->trendyol_source)) {
            if (preg_match('/merchantId=(\d+)/', $this->trendyol_source, $m)) {
                $contentId = $m[1];
            }
        }
        return $contentId;
    }
}
