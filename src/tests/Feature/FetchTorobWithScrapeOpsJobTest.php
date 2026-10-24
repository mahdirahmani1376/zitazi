<?php

namespace Tests\Feature;

use App\Jobs\FetchTorobWithScrapeOpsJob;
use App\Services\ScrapeOpsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FetchTorobWithScrapeOpsJobTest extends TestCase
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

        (new FetchTorobWithScrapeOpsJob(self::DATA))->handle(app(ScrapeOpsService::class));

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

        (new FetchTorobWithScrapeOpsJob(self::DATA))->handle(app(ScrapeOpsService::class));

        Http::assertNothingSent();

        Redis::shouldHaveReceived('rpush')
            ->once()
            ->with('scrape_result', \Mockery::on(function ($json) {
                return data_get(json_decode($json, true), 'success') === true;
            }));

        $this->assertSame(0, (int) Cache::get('scrapeops:requests', 0));
    }

    public function test_quota_reached_returns_failure_without_http_call(): void
    {
        Cache::put('scrapeops:requests', 800);

        (new FetchTorobWithScrapeOpsJob(self::DATA))->handle(app(ScrapeOpsService::class));

        Http::assertNothingSent();

        Redis::shouldHaveReceived('rpush')
            ->once()
            ->with('scrape_result', \Mockery::on(function ($json) {
                $result = json_decode($json, true);

                return data_get($result, 'success') === false
                    && data_get($result, 'blocked') === false
                    && data_get($result, 'error.name') === 'ScrapeOpsQuotaExceeded';
            }));
    }

    public function test_404_marks_product_deleted(): void
    {
        Http::fake([
            'proxy.scrapeops.io/*' => Http::response('Not Found', 404),
        ]);

        (new FetchTorobWithScrapeOpsJob(self::DATA))->handle(app(ScrapeOpsService::class));

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

        (new FetchTorobWithScrapeOpsJob(self::DATA))->handle(app(ScrapeOpsService::class));

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

        (new FetchTorobWithScrapeOpsJob($data))->handle(app(ScrapeOpsService::class));

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

        (new FetchTorobWithScrapeOpsJob(self::DATA))->handle(app(ScrapeOpsService::class));

        Sleep::assertSlept(fn ($duration) => (int) $duration->totalSeconds === 7, 1);

        Http::assertSentCount(1);

        Redis::shouldHaveReceived('rpush')
            ->with('scrape_result', \Mockery::type('string'))
            ->once();
    }
}
