<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\StoreHealthService;
use App\Services\WoocommerceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WoocommerceServiceHealthAwareTest extends TestCase
{
    private const ZITAZI_PRIMARY = 'http://zitazi.test';

    private const ZITAZI_FALLBACK = 'https://fallback.test';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'services.zitazi.base_url' => self::ZITAZI_PRIMARY,
            'services.zitazi.fallback_url' => self::ZITAZI_FALLBACK,
            'services.zitazi.security_key' => 'key',
            'services.zitazi.security_pass' => 'pass',
        ]);
    }

    public function test_send_request_uses_primary_url_by_default(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        WoocommerceService::sendRequest('products/1', [], 'get', Product::ZITAZI);

        Http::assertSent(fn ($request) => $request->url() === self::ZITAZI_PRIMARY.'/wp-json/wc/v3/products/1');
    }

    public function test_send_request_uses_fallback_url_when_activated(): void
    {
        Cache::forever(StoreHealthService::FALLBACK_ACTIVE_KEY, true);

        Http::fake(['*' => Http::response([], 200)]);

        WoocommerceService::sendRequest('products/1', [], 'get', Product::ZITAZI);

        Http::assertSent(fn ($request) => $request->url() === self::ZITAZI_FALLBACK.'/wp-json/wc/v3/products/1');
    }

    public function test_fallback_override_does_not_affect_satreh(): void
    {
        Cache::forever(StoreHealthService::FALLBACK_ACTIVE_KEY, true);

        config(['services.satreh.base_url' => 'http://satreh.test']);

        config([
            'services.satreh.security_key' => 'key',
            'services.satreh.security_pass' => 'pass',
        ]);

        Http::fake(['*' => Http::response([], 200)]);

        WoocommerceService::sendRequest('products/1', [], 'get', Product::SATRE);

        Http::assertSent(fn ($request) => $request->url() === 'http://satreh.test/wp-json/wc/v3/products/1');
    }
}
