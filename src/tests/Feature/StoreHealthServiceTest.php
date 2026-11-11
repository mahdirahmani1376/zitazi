<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\StoreHealthService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoreHealthServiceTest extends TestCase
{
    private const ZITAZI_PRIMARY = 'http://zitazi.test';

    private const ZITAZI_FALLBACK = 'https://fallback.test';

    private const SATREH_PRIMARY = 'http://satreh.test';

    private StoreHealthService $service;

    private int $zitaziStatus = 200;

    private int $satrehStatus = 200;

    private bool $faked = false;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'services.zitazi.base_url' => self::ZITAZI_PRIMARY,
            'services.zitazi.fallback_url' => self::ZITAZI_FALLBACK,
            'services.satreh.base_url' => self::SATREH_PRIMARY,
            'services.satreh_sync_enabled' => true,
            'services.telegram.bot_token' => 'bot-token',
            'services.telegram.chat_id' => '-1003953278794',
            'services.store_health.endpoint' => '/wp-json/wc/v3',
        ]);

        $this->service = app(StoreHealthService::class);
    }

    /**
     * Register the fakes once: Http::fake() merges stubs, so re-faking would
     * leave the first (stale) status responses winning.
     */
    private function fakeStores(int $zitaziStatus, int $satrehStatus): void
    {
        $this->zitaziStatus = $zitaziStatus;
        $this->satrehStatus = $satrehStatus;

        if ($this->faked) {
            return;
        }

        $this->faked = true;

        Http::fake([
            self::ZITAZI_PRIMARY.'/wp-json/wc/v3' => fn () => Http::response([], $this->zitaziStatus),
            self::SATREH_PRIMARY.'/wp-json/wc/v3' => fn () => Http::response([], $this->satrehStatus),
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
    }

    private function assertTelegramSentContaining(string $needle): void
    {
        Http::assertSent(function ($request) use ($needle) {
            return str_contains($request->url(), 'api.telegram.org')
                && str_contains((string) $request['text'], $needle);
        });
    }

    private function telegramRequests(): int
    {
        return Http::recorded(fn ($request) => str_contains($request->url(), 'api.telegram.org'))->count();
    }

    public function test_healthy_stores_leave_state_untouched(): void
    {
        $this->fakeStores(200, 200);

        $results = $this->service->run();

        $this->assertTrue($results[Product::ZITAZI]['healthy']);
        $this->assertTrue($results[Product::SATRE]['healthy']);
        $this->assertFalse(StoreHealthService::isFallbackActive());
        $this->assertSame(0, $this->telegramRequests());
        $this->assertSame(self::ZITAZI_PRIMARY, StoreHealthService::baseUrlFor(Product::ZITAZI));
        $this->assertSame(self::SATREH_PRIMARY, StoreHealthService::baseUrlFor(Product::SATRE));
    }

    public function test_zitazi_failure_activates_fallback_and_alerts(): void
    {
        $this->fakeStores(503, 200);

        $this->service->run();

        $this->assertTrue(StoreHealthService::isFallbackActive());
        $this->assertSame(self::ZITAZI_FALLBACK, StoreHealthService::baseUrlFor(Product::ZITAZI));
        $this->assertSame(1, $this->telegramRequests());
        $this->assertTelegramSentContaining('Zitazi store is DOWN');
    }

    public function test_connection_error_is_treated_as_down(): void
    {
        Http::fake([
            self::ZITAZI_PRIMARY.'/wp-json/wc/v3' => fn () => throw new ConnectionException('timed out'),
            self::SATREH_PRIMARY.'/wp-json/wc/v3' => Http::response([], 200),
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $results = $this->service->run();

        $this->assertFalse($results[Product::ZITAZI]['healthy']);
        $this->assertNull($results[Product::ZITAZI]['status']);
        $this->assertTrue(StoreHealthService::isFallbackActive());
    }

    public function test_zitazi_recovery_restores_primary_and_alerts_once(): void
    {
        Cache::forever(StoreHealthService::ZITAZI_DOWN_KEY, true);
        Cache::forever(StoreHealthService::FALLBACK_ACTIVE_KEY, true);

        $this->fakeStores(200, 200);

        $this->service->run();

        $this->assertFalse(StoreHealthService::isFallbackActive());
        $this->assertSame(self::ZITAZI_PRIMARY, StoreHealthService::baseUrlFor(Product::ZITAZI));
        $this->assertSame(1, $this->telegramRequests());
        $this->assertTelegramSentContaining('Zitazi store recovered');
    }

    public function test_repeated_failures_send_only_one_alert(): void
    {
        $this->fakeStores(500, 200);

        $this->service->run();
        $this->service->run();
        $this->service->run();

        $this->assertSame(1, $this->telegramRequests());
    }

    public function test_satreh_failure_disables_sync_and_alerts(): void
    {
        $this->fakeStores(200, 502);

        $this->service->run();

        $this->assertFalse((bool) Cache::get('sync_satreh_enabled'));
        $this->assertSame(1, $this->telegramRequests());
        $this->assertTelegramSentContaining('Satreh store is DOWN');
    }

    public function test_satreh_recovery_re_enables_sync_and_alerts(): void
    {
        $this->fakeStores(200, 500);
        $this->service->run();

        $this->fakeStores(200, 200);
        $this->service->run();

        $this->assertTrue((bool) Cache::get('sync_satreh_enabled'));
        $this->assertTelegramSentContaining('Satreh store recovered');
        $this->assertFalse((bool) Cache::get(StoreHealthService::SATREH_DOWN_KEY));
    }

    public function test_satreh_recovery_keeps_a_manual_disable(): void
    {
        Cache::forever('sync_satreh_enabled', false);

        $this->fakeStores(200, 500);
        $this->service->run();

        $this->fakeStores(200, 200);
        $this->service->run();

        $this->assertFalse((bool) Cache::get('sync_satreh_enabled'));
    }

    public function test_missing_base_url_marks_store_down_without_http_call(): void
    {
        config(['services.satreh.base_url' => null]);

        $this->fakeStores(200, 200);

        $results = $this->service->run();

        $this->assertFalse($results[Product::SATRE]['healthy']);
        $this->assertSame('base url is not configured', $results[Product::SATRE]['error']);
    }
}
