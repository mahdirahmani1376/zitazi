<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductFilterCacheService
{
    public const CATEGORY_OPTIONS_KEY = 'filament.product-filters.category-options';

    public const BRAND_OPTIONS_KEY = 'filament.product-filters.brand-options';

    public const OWNER_OPTIONS_KEY = 'filament.product-filters.owner-options';

    private const TTL_SECONDS = 3600;

    /** @return array<string, string> */
    public function categoryOptions(): array
    {
        return Cache::remember(self::CATEGORY_OPTIONS_KEY, self::TTL_SECONDS, function () {
            return Product::query()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct('category')
                ->orderBy('category')
                ->pluck('category', 'category')
                ->toArray();
        });
    }

    /** @return array<string, string> */
    public function brandOptions(): array
    {
        return Cache::remember(self::BRAND_OPTIONS_KEY, self::TTL_SECONDS, function () {
            return Product::query()
                ->whereNotNull('brand')
                ->where('brand', '!=', '')
                ->distinct('brand')
                ->orderBy('brand')
                ->pluck('brand', 'brand')
                ->toArray();
        });
    }

    /** @return array<string, string> */
    public function ownerOptions(): array
    {
        return Cache::remember(self::OWNER_OPTIONS_KEY, self::TTL_SECONDS, function () {
            return Product::query()
                ->whereNotNull('owner')
                ->where('owner', '!=', '')
                ->distinct('owner')
                ->orderBy('owner')
                ->pluck('owner', 'owner')
                ->toArray();
        });
    }

    public function forget(): void
    {
        Cache::forget(self::CATEGORY_OPTIONS_KEY);
        Cache::forget(self::BRAND_OPTIONS_KEY);
        Cache::forget(self::OWNER_OPTIONS_KEY);
    }
}
