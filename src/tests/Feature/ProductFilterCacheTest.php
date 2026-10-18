<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ProductFilterCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductFilterCacheTest extends TestCase
{
    use RefreshDatabase;

    private ProductFilterCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ProductFilterCacheService::class);
    }

    public function test_filter_options_are_cached_and_returned(): void
    {
        Product::factory()->create([
            'own_id' => 'cache-test-1',
            'category' => 'Electronics',
            'brand' => 'Apple',
            'owner' => 'Mahdi',
        ]);

        $categories = $this->service->categoryOptions();
        $brands = $this->service->brandOptions();
        $owners = $this->service->ownerOptions();

        $this->assertSame(['Electronics' => 'Electronics'], $categories);
        $this->assertSame(['Apple' => 'Apple'], $brands);
        $this->assertSame(['Mahdi' => 'Mahdi'], $owners);

        $this->assertTrue(Cache::has(ProductFilterCacheService::CATEGORY_OPTIONS_KEY));
        $this->assertTrue(Cache::has(ProductFilterCacheService::BRAND_OPTIONS_KEY));
        $this->assertTrue(Cache::has(ProductFilterCacheService::OWNER_OPTIONS_KEY));

        $this->assertSame($categories, $this->service->categoryOptions());
    }

    public function test_product_create_invalidates_filter_options_cache(): void
    {
        Cache::put(ProductFilterCacheService::CATEGORY_OPTIONS_KEY, ['old'], 3600);
        Cache::put(ProductFilterCacheService::BRAND_OPTIONS_KEY, ['old'], 3600);
        Cache::put(ProductFilterCacheService::OWNER_OPTIONS_KEY, ['old'], 3600);

        Product::factory()->create([
            'own_id' => 'cache-test-create',
            'category' => 'Electronics',
            'brand' => 'Apple',
            'owner' => 'Mahdi',
        ]);

        $this->assertFalse(Cache::has(ProductFilterCacheService::CATEGORY_OPTIONS_KEY));
        $this->assertFalse(Cache::has(ProductFilterCacheService::BRAND_OPTIONS_KEY));
        $this->assertFalse(Cache::has(ProductFilterCacheService::OWNER_OPTIONS_KEY));
    }

    public function test_filter_relevant_product_update_invalidates_filter_options_cache(): void
    {
        $product = Product::factory()->create([
            'own_id' => 'cache-test-update',
            'category' => 'Electronics',
            'brand' => 'Apple',
            'owner' => 'Mahdi',
        ]);

        $this->service->categoryOptions();
        $this->service->brandOptions();
        $this->service->ownerOptions();

        $product->update([
            'category' => 'Home Appliances',
        ]);

        $this->assertFalse(Cache::has(ProductFilterCacheService::CATEGORY_OPTIONS_KEY));
        $this->assertFalse(Cache::has(ProductFilterCacheService::BRAND_OPTIONS_KEY));
        $this->assertFalse(Cache::has(ProductFilterCacheService::OWNER_OPTIONS_KEY));
    }

    public function test_unrelated_product_update_keeps_filter_options_cache(): void
    {
        $product = Product::factory()->create([
            'own_id' => 'cache-test-unrelated',
            'category' => 'Electronics',
            'brand' => 'Apple',
            'owner' => 'Mahdi',
        ]);

        $this->service->categoryOptions();
        $this->service->brandOptions();
        $this->service->ownerOptions();

        $product->update([
            'markup' => 25,
        ]);

        $this->assertTrue(Cache::has(ProductFilterCacheService::CATEGORY_OPTIONS_KEY));
        $this->assertTrue(Cache::has(ProductFilterCacheService::BRAND_OPTIONS_KEY));
        $this->assertTrue(Cache::has(ProductFilterCacheService::OWNER_OPTIONS_KEY));
    }

    public function test_product_delete_invalidates_filter_options_cache(): void
    {
        $product = Product::factory()->create([
            'own_id' => 'cache-test-delete',
            'category' => 'Electronics',
            'brand' => 'Apple',
            'owner' => 'Mahdi',
        ]);

        $this->service->categoryOptions();
        $this->service->brandOptions();
        $this->service->ownerOptions();

        $product->delete();

        $this->assertFalse(Cache::has(ProductFilterCacheService::CATEGORY_OPTIONS_KEY));
        $this->assertFalse(Cache::has(ProductFilterCacheService::BRAND_OPTIONS_KEY));
        $this->assertFalse(Cache::has(ProductFilterCacheService::OWNER_OPTIONS_KEY));
    }
}
