<?php

namespace Tests\Feature;

use App\Facades\TorobServiceFacade;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class ListenForTorobCommandTest extends TestCase
{
    private const DATA = [
        'product' => ['id' => 42, 'full_url' => 'https://api.torob.com/v4/base-product/details/?prk=abc123'],
        'bulk' => false,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Redis::spy();

        // Keep the inter-request pause out of the way unless a test opts in.
        config(['services.scrapeops.sleep_seconds' => 0]);
    }

    private function runOnce(array $message): void
    {
        Redis::shouldReceive('blpop')
            ->once()
            ->with('torob_scrape_product', 0)
            ->andReturn(['torob_scrape_product', json_encode($message)]);

        $this->artisan('listen:torob', ['--once' => true])->run();
    }

    public function test_success_caches_response_and_pushes_result(): void
    {
        Http::fake([
            'proxy.scrapeops.io/*' => Http::response([
                'products_info' => [
                    'result' => [
                        ['price' => 1000, 'shop_id' => 1],
                        ['price' => 900, 'shop_id' => 2],
                    ],
                ],
            ], 200),
        ]);

        $this->runOnce(self::DATA);

        Redis::shouldHaveReceived('rpush')
            ->once()
            ->with('scrape_result', \Mockery::on(function ($json) {
                $result = json_decode($json, true);

                return data_get($result, 'success') === true
                    && data_get($result, 'source') === 'Torob'
                    && data_get($result, 'product_id') === 42
                    && data_get($result, 'response_data.result') !== null;
            }));

        $this->assertTrue(Cache::has('torob:scrapeops:42'));
        $this->assertSame(1, (int) Cache::get('scrapeops:requests'));
    }

    public function test_cache_hit_does_not_call_http(): void
    {
        Cache::put('torob:scrapeops:42', ['result' => [['price' => 500]]], now()->addMonth());

        $this->runOnce(self::DATA);

        Http::assertNothingSent();

        Redis::shouldHaveReceived('rpush')
            ->once()
            ->with('scrape_result', \Mockery::on(function ($json) {
                return data_get(json_decode($json, true), 'success') === true;
            }));

        $this->assertSame(0, (int) Cache::get('scrapeops:requests', 0));
    }

    public function test_404_marks_product_deleted(): void
    {
        Http::fake([
            'proxy.scrapeops.io/*' => Http::response('Not Found', 404),
        ]);

        $this->runOnce(self::DATA);

        Redis::shouldHaveReceived('rpush')
            ->once()
            ->with('scrape_result', \Mockery::on(function ($json) {
                $result = json_decode($json, true);

                return data_get($result, 'success') === false
                    && data_get($result, 'deleted') === true;
            }));
    }

    public function test_non_json_response_returns_failure(): void
    {
        Http::fake([
            'proxy.scrapeops.io/*' => Http::response('<html>challenge</html>', 200),
        ]);

        $this->runOnce(self::DATA);

        Redis::shouldHaveReceived('rpush')
            ->once()
            ->with('scrape_result', \Mockery::on(function ($json) {
                $result = json_decode($json, true);

                return data_get($result, 'success') === false
                    && data_get($result, 'error.name') === 'ScrapeOpsInvalidResponse';
            }));
    }

    public function test_empty_full_url_returns_failure_without_http_call(): void
    {
        $data = ['product' => ['id' => 42, 'full_url' => ''], 'bulk' => false];

        $this->runOnce($data);

        Http::assertNothingSent();

        Redis::shouldHaveReceived('rpush')
            ->once()
            ->with('scrape_result', \Mockery::on(function ($json) {
                return data_get(json_decode($json, true), 'success') === false;
            }));
    }

    public function test_sleeps_between_requests_before_http_call(): void
    {
        Http::fake([
            'proxy.scrapeops.io/*' => Http::response(['products_info' => ['result' => []]], 200),
        ]);

        config(['services.scrapeops.sleep_seconds' => 7]);

        Sleep::fake();

        $this->runOnce(self::DATA);

        Sleep::assertSlept(fn ($duration) => (int) $duration->totalSeconds === 7, 1);

        Http::assertSentCount(1);

        Redis::shouldHaveReceived('rpush')
            ->with('scrape_result', \Mockery::type('string'))
            ->once();
    }

    public function test_second_shop_min_price_when_zitazi_has_min_price()
    {
        $responseSample = File::json(base_path('tests/Data/torob-zitazi-first-data.json'));

        $product = Product::factory()->create();

        $data =  [
            'product_id' => $product->id,
            'response_data' => $responseSample['products_info'],
            'response_status' => 200,
            'headers' => [],
            'url' => '',
            'full_url' => '',
            'success' => true,
            'source' => 'Torob',
            'bulk' => false,
        ];

        TorobServiceFacade::processResponse($product,$data);

        $this->assertDatabaseHas('products',[
           'rival_min_price' => 2000000,
           'id' => $product->id,
        ]);
    }
}
