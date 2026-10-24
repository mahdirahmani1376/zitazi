<?php

namespace Tests\Feature;

use App\Services\ScrapeOpsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapeOpsServiceTest extends TestCase
{
    private ScrapeOpsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ScrapeOpsService::class);
    }

    public function test_get_proxies_the_url_through_scrapeops(): void
    {
        Http::fake([
            'proxy.scrapeops.io/*' => Http::response(['products_info' => ['result' => []]], 200),
        ]);

        $target = 'https://api.torob.com/v4/base-product/details/?prk=abc123';

        $response = $this->service->get($target);

        $this->assertSame(200, $response['status']);
        $this->assertSame(['products_info' => ['result' => []]], $response['json']);

        Http::assertSent(function ($request) use ($target) {
            return $request->url() === 'https://proxy.scrapeops.io/v1/?url='.urlencode($target)
                && $request->hasHeader('api_key')
                && $request->header('api_key')[0] === config('services.scrapeops.key')
                && $request->hasHeader('Accept-Encoding')
                && $request->header('Accept-Encoding')[0] === 'gzip';
        });
    }

    public function test_get_returns_null_json_for_non_json_body(): void
    {
        Http::fake([
            'proxy.scrapeops.io/*' => Http::response('<html>challenge</html>', 403),
        ]);

        $response = $this->service->get('https://api.torob.com/anything');

        $this->assertSame(403, $response['status']);
        $this->assertNull($response['json']);
    }
}
