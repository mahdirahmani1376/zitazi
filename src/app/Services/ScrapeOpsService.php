<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Lightweight HTTP proxy through ScrapeOps (proxy.scrapeops.io).
 *
 * Used to fetch Torob API responses server-side instead of going through the
 * Puppeteer scraper, while the free plan lasts. The ScrapeOps endpoint is a
 * plain GET proxy: https://proxy.scrapeops.io/v1/?url=<encoded target>,
 * authenticated with the `api_key` header.
 */
class ScrapeOpsService
{
    /**
     * Proxy a GET request for $url through ScrapeOps.
     *
     * @return array{status: int, headers: array, body: string, json: array|null}
     */
    public function get(string $url): array
    {
        $response = Http::withHeaders([
            'api_key' => config('services.scrapeops.key'),
            // ScrapeOps responds with brotli by default; a truncated br stream
            // surfaces as cURL error 61 (CURLE_BAD_CONTENT_ENCODING). Request
            // gzip instead, which the proxy also supports and decodes reliably.
            'Accept-Encoding' => 'gzip',
        ])
            ->connectTimeout(15)
            ->timeout(90)
            ->get('https://proxy.scrapeops.io/v1/', ['url' => $url]);

        return [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->body(),
            'json' => $response->json(),
        ];
    }
}
