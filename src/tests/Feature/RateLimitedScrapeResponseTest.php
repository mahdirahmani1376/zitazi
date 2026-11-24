<?php

namespace Tests\Feature;

use App\Enums\SyncStatusEnum;
use App\Jobs\BatchSyncZitaziJob;
use App\Jobs\SyncZitaziJob;
use App\Models\LogModel;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * An anti-bot block (Cloudflare 403 / challenge) must only move the product to
 * `rate_limited`. Stock, price and variations have to stay untouched, otherwise
 * a temporary block empties the destination store.
 */
class RateLimitedScrapeResponseTest extends TestCase
{
    private function makeDecathlonProduct(): Product
    {
        return Product::factory()->create([
            'decathlon_url' => 'https://www.decathlon.com.tr/p/_/R-p-312061?mc=8569298',
            'trendyol_source' => '',
            'min_price' => 1000,
            'base_source' => Product::ZITAZI,
        ]);
    }

    private function makeVariation(Product $product, array $attributes = []): Variation
    {
        return Variation::query()->create($attributes + [
            'product_id' => $product->id,
            'source' => Product::SOURCE_DECATHLON,
            'source_product_id' => '312061',
            'source_variation_id' => '8569298',
            'sku' => '2954559',
            'status' => Variation::AVAILABLE,
            'stock' => 88,
            'price' => 3800,
            'rial_price' => 9000000,
            'base_source' => Product::ZITAZI,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function dispatchBlockedResponse(Product $product, array $overrides = []): void
    {
        $message = array_merge([
            'product_id' => $product->id,
            'success' => false,
            'blocked' => true,
            'block_reason' => 'http_403',
            'response_status' => 403,
            'retry_count' => 1,
            'source' => 'Decathlon',
            'bulk' => false,
        ], $overrides);

        Redis::shouldReceive('blpop')
            ->once()
            ->with('scrape_result', 0)
            ->andReturn(['scrape_result', json_encode($message)]);

        $this->artisan('listen:scrape', ['--once' => true])->run();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Redis::spy();
        Bus::fake();
    }

    public function test_blocked_response_marks_product_rate_limited(): void
    {
        $product = $this->makeDecathlonProduct();

        $this->dispatchBlockedResponse($product);

        $this->assertSame(
            SyncStatusEnum::RATE_LIMITED,
            $product->fresh()->sync_status
        );
    }

    public function test_blocked_response_leaves_variation_stock_and_price_untouched(): void
    {
        $product = $this->makeDecathlonProduct();
        $variation = $this->makeVariation($product);

        $this->dispatchBlockedResponse($product);

        $variation->refresh();

        $this->assertSame(88, (int) $variation->stock);
        $this->assertSame(Variation::AVAILABLE, $variation->status);
        $this->assertSame(9000000, (int) $variation->rial_price);
        $this->assertNull($variation->deleted_at, 'a rate limit must never soft-delete variations');
    }

    public function test_blocked_response_is_logged_for_tracing(): void
    {
        $product = $this->makeDecathlonProduct();

        $this->dispatchBlockedResponse($product, ['block_reason' => 'all_stock_zero_suspicious']);

        $log = LogModel::query()
            ->where('product_id', $product->id)
            ->where('message', 'scrape rate limited')
            ->first();

        $this->assertNotNull($log, 'a "scrape rate limited" log entry must be created');
        $this->assertSame('all_stock_zero_suspicious', data_get($log->data, 'block_reason'));
    }

    public function test_blocked_response_does_not_dispatch_sync_jobs(): void
    {
        $product = $this->makeDecathlonProduct();
        $this->makeVariation($product);

        $this->dispatchBlockedResponse($product);

        // SyncZitaziJob must never be dispatched for a blocked product.
        // BroadcastEvent (ProductSyncStatusChangedEvent) is expected.
        Bus::assertNotDispatched(SyncZitaziJob::class);
        Bus::assertNotDispatched(BatchSyncZitaziJob::class);
    }
}
