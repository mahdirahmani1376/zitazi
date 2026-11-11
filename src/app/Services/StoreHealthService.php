<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hourly reachability check for the two WooCommerce destination stores.
 *
 * Zitazi: when the primary (`services.zitazi.base_url`) is unreachable, syncs
 * temporarily fall back to `services.zitazi.fallback_url` until a check
 * succeeds again.
 *
 * Satreh: when the store is unreachable, `sync_satreh_enabled` is turned off
 * so nothing is queued against a dead store, and restored when it recovers.
 *
 * Alerts are sent to Telegram only on state changes (down / recovered) so an
 * extended outage does not produce an hourly stream of identical messages.
 */
class StoreHealthService
{
    public const FALLBACK_ACTIVE_KEY = 'store_health:zitazi:fallback_active';

    public const ZITAZI_DOWN_KEY = 'store_health:zitazi:down';

    public const SATREH_DOWN_KEY = 'store_health:satreh:down';

    public const SATREH_PREVIOUS_SYNC_KEY = 'store_health:satreh:previous_sync_enabled';

    /** @var array<string, string> */
    public const LABELS = [
        Product::ZITAZI => 'Zitazi',
        Product::SATRE => 'Satreh',
    ];

    public function __construct(
        private readonly TelegramService $telegram,
    ) {}

    /**
     * Probe both stores and apply the resulting state transitions.
     *
     * @return array<string, array{healthy: bool, status: int|null, error: string|null, url: string}>
     */
    public function run(): array
    {
        $results = [
            Product::ZITAZI => $this->probe(Product::ZITAZI),
            Product::SATRE => $this->probe(Product::SATRE),
        ];

        $this->applyZitazi($results[Product::ZITAZI]);
        $this->applySatreh($results[Product::SATRE]);

        return $results;
    }

    /**
     * Hit the store's WooCommerce REST index without credentials.
     *
     * @return array{healthy: bool, status: int|null, error: string|null, url: string}
     */
    public function probe(string $source): array
    {
        $url = $this->healthUrl($source);

        if ($url === null) {
            return [
                'healthy' => false,
                'status' => null,
                'error' => 'base url is not configured',
                'url' => '',
            ];
        }

        try {
            $response = Http::connectTimeout(config('services.store_health.connect_timeout', 5))
                ->timeout(config('services.store_health.timeout', 15))
                ->acceptJson()
                ->get($url);

            return [
                'healthy' => $response->ok(),
                'status' => $response->status(),
                'error' => $response->ok() ? null : 'unexpected status code',
                'url' => $url,
            ];
        } catch (Throwable $e) {
            return [
                'healthy' => false,
                'status' => null,
                'error' => $e->getMessage(),
                'url' => $url,
            ];
        }
    }

    /**
     * The base URL every destination-store request should currently use.
     */
    public static function baseUrlFor(string $source): ?string
    {
        if ($source === Product::ZITAZI && self::isFallbackActive()) {
            return config('services.zitazi.fallback_url') ?: config('services.zitazi.base_url');
        }

        return config($source === Product::SATRE ? 'services.satreh.base_url' : 'services.zitazi.base_url');
    }

    public static function isFallbackActive(): bool
    {
        return filter_var(Cache::get(self::FALLBACK_ACTIVE_KEY, false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array{healthy: bool, status: int|null, error: string|null, url: string}  $result
     */
    private function applyZitazi(array $result): void
    {
        $wasDown = filter_var(Cache::get(self::ZITAZI_DOWN_KEY, false), FILTER_VALIDATE_BOOLEAN);

        if ($result['healthy']) {
            $fallbackWasActive = self::isFallbackActive();

            Cache::forever(self::ZITAZI_DOWN_KEY, false);
            Cache::forever(self::FALLBACK_ACTIVE_KEY, false);

            if ($wasDown || $fallbackWasActive) {
                Log::info('zitazi store recovered; primary base url restored', [
                    'url' => $result['url'],
                    'status' => $result['status'],
                ]);

                $this->telegram->send(implode("\n", [
                    '✅ <b>Zitazi store recovered</b>',
                    'URL: <code>'.TelegramService::escape($result['url']).'</code>',
                    'Status: <code>'.($result['status'] ?? 'n/a').'</code>',
                    'Primary base URL restored: <code>'.TelegramService::escape((string) config('services.zitazi.base_url')).'</code>',
                ]));
            }

            return;
        }

        Cache::forever(self::ZITAZI_DOWN_KEY, true);
        Cache::forever(self::FALLBACK_ACTIVE_KEY, true);

        if (! $wasDown) {
            Log::error('zitazi store is unreachable; fallback base url activated', [
                'url' => $result['url'],
                'status' => $result['status'],
                'error' => $result['error'],
                'fallback_url' => config('services.zitazi.fallback_url'),
            ]);

            $this->telegram->send(implode("\n", [
                '🚨 <b>Zitazi store is DOWN</b>',
                'URL: <code>'.TelegramService::escape($result['url']).'</code>',
                'Status: <code>'.($result['status'] ?? 'no response').'</code>',
                'Error: <code>'.TelegramService::escape((string) $result['error']).'</code>',
                'Fallback base URL activated: <code>'.TelegramService::escape((string) config('services.zitazi.fallback_url')).'</code>',
            ]));
        }
    }

    /**
     * @param  array{healthy: bool, status: int|null, error: string|null, url: string}  $result
     */
    private function applySatreh(array $result): void
    {
        $wasDown = filter_var(Cache::get(self::SATREH_DOWN_KEY, false), FILTER_VALIDATE_BOOLEAN);

        if ($result['healthy']) {
            if (! $wasDown) {
                return;
            }

            $previous = Cache::get(self::SATREH_PREVIOUS_SYNC_KEY, true);

            Cache::forever(self::SATREH_DOWN_KEY, false);
            Cache::forever('sync_satreh_enabled', filter_var($previous, FILTER_VALIDATE_BOOLEAN));
            Cache::forget(self::SATREH_PREVIOUS_SYNC_KEY);

            Log::info('satreh store recovered; sync_satreh_enabled restored', [
                'url' => $result['url'],
                'status' => $result['status'],
                'sync_satreh_enabled' => filter_var($previous, FILTER_VALIDATE_BOOLEAN),
            ]);

            $this->telegram->send(implode("\n", [
                '✅ <b>Satreh store recovered</b>',
                'URL: <code>'.TelegramService::escape($result['url']).'</code>',
                'Status: <code>'.($result['status'] ?? 'n/a').'</code>',
                'Satreh sync re-enabled: <code>'.(filter_var($previous, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false').'</code>',
            ]));

            return;
        }

        if ($wasDown) {
            return;
        }

        // Remember the effective value at outage time so a manual disable made
        // in the admin widget is not silently switched back on at recovery.
        if (! Cache::has(self::SATREH_PREVIOUS_SYNC_KEY)) {
            Cache::forever(
                self::SATREH_PREVIOUS_SYNC_KEY,
                filter_var(
                    Cache::get('sync_satreh_enabled', config('services.satreh_sync_enabled')),
                    FILTER_VALIDATE_BOOLEAN,
                ),
            );
        }

        Cache::forever(self::SATREH_DOWN_KEY, true);
        Cache::forever('sync_satreh_enabled', false);

        Log::error('satreh store is unreachable; sync_satreh_enabled disabled', [
            'url' => $result['url'],
            'status' => $result['status'],
            'error' => $result['error'],
        ]);

        $this->telegram->send(implode("\n", [
            '🚨 <b>Satreh store is DOWN</b>',
            'URL: <code>'.TelegramService::escape($result['url']).'</code>',
            'Status: <code>'.($result['status'] ?? 'no response').'</code>',
            'Error: <code>'.TelegramService::escape((string) $result['error']).'</code>',
            'Satreh sync disabled: <code>sync_satreh_enabled = false</code>',
        ]));
    }

    private function healthUrl(string $source): ?string
    {
        $baseUrl = rtrim((string) config($source === Product::SATRE ? 'services.satreh.base_url' : 'services.zitazi.base_url'), '/');

        if ($baseUrl === '') {
            return null;
        }

        return $baseUrl.'/'.ltrim((string) config('services.store_health.endpoint', '/wp-json/wc/v3'), '/');
    }
}
