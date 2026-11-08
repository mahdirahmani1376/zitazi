<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Variation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResyncDeletedVariationsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.zitazi.base_url' => 'https://zitazi.test',
            'services.zitazi.security_key' => 'key',
            'services.zitazi.security_pass' => 'pass',
        ]);
    }

    private function makeProduct(array $attributes = []): Product
    {
        return Product::factory()->create($attributes + [
            'own_id' => '1001',
            'base_source' => Product::ZITAZI,
        ]);
    }

    private function makeVariation(Product $product, array $attributes = []): Variation
    {
        $variation = Variation::query()->create($attributes + [
            'product_id' => $product->id,
            'base_source' => Product::ZITAZI,
            'own_id' => '5001',
        ]);

        $variation->delete();

        return $variation;
    }

    public function test_it_posts_out_of_stock_update_to_the_variation_endpoint(): void
    {
        Http::fake(['zitazi.test/*' => Http::response(['id' => 5001], 200)]);

        $product = $this->makeProduct();
        $this->makeVariation($product);

        $this->artisan('app:resync-deleted-variations')->assertExitCode(0);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://zitazi.test/wp-json/wc/v3/products/1001/variations/5001'
                && $request['stock_status'] === 'outofstock'
                && $request['stock_quantity'] === 0;
        });
    }

    public function test_it_falls_back_to_the_product_endpoint_for_a_single_variation_product(): void
    {
        Http::fake(['zitazi.test/*' => Http::response(['id' => 1001], 200)]);

        $product = $this->makeProduct();
        $this->makeVariation($product, ['own_id' => null, 'item_type' => 'variation']);

        $this->artisan('app:resync-deleted-variations')->assertExitCode(0);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://zitazi.test/wp-json/wc/v3/products/1001'
                && $request['stock_status'] === 'outofstock';
        });
    }

    public function test_it_skips_variations_without_a_safe_route(): void
    {
        Http::fake();

        $product = $this->makeProduct();
        $this->makeVariation($product, ['own_id' => null, 'item_type' => 'variation']);
        $this->makeVariation($product, ['own_id' => null, 'item_type' => 'variation']);

        $this->artisan('app:resync-deleted-variations')
            ->expectsOutputToContain('no route to resync')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_dry_run_sends_no_requests(): void
    {
        Http::fake();

        $product = $this->makeProduct();
        $this->makeVariation($product);

        $this->artisan('app:resync-deleted-variations', ['--dry-run' => true])
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_it_returns_failure_when_the_request_is_rejected(): void
    {
        Http::fake(['zitazi.test/*' => Http::response(['message' => 'nope'], 400)]);

        $product = $this->makeProduct();
        $this->makeVariation($product);

        $this->artisan('app:resync-deleted-variations', ['--retries' => 0])
            ->assertExitCode(1);

        Http::assertSentCount(1);
    }

    public function test_it_only_resyncs_the_requested_source(): void
    {
        Http::fake(['zitazi.test/*' => Http::response([], 200)]);

        $product = $this->makeProduct();
        $this->makeVariation($product, ['base_source' => Product::SATRE]);

        $this->artisan('app:resync-deleted-variations', ['--source' => Product::ZITAZI])
            ->expectsOutputToContain('Nothing to resync')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_it_rejects_an_invalid_source_option(): void
    {
        $this->artisan('app:resync-deleted-variations', ['--source' => 'decathlon'])
            ->assertExitCode(1);
    }
}
