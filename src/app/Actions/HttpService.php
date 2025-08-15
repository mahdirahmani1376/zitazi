<?php

namespace App\Actions;

use App\Exceptions\UnProcessableResponseException;
use App\Models\Product;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpService
{
    public function sendWithCache($method, $url)
    {
        if (empty($headers)) {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:139.0) Gecko/20100101 Firefox/139.0',
            ];
        }

        $cacheKey = md5($url);
        if ($response = Cache::get($cacheKey)) {
            return $response;
        }

        $response = Http::withHeaders($headers)->$method($url);
        if ($response->successful()) {
            /** @var Response $response */
            Cache::put($cacheKey, $response->json(), now()->addDay());
        } else {
            Log::error('error-in-sendWithCache', [
                'url' => $url,
                'error' => $response->json()
            ]);
            throw new UnProcessableResponseException('Unprocessable response sendWithCache');
        }

        return $response->json();
    }

    public function sendTorobRequest(Product $product)
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.4 Safari/605.1.15',
            'Mozilla/5.0 (Linux; Android 11; SM-A505F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Mobile Safari/537.36',
        ];

        $headers = [
            'User-Agent' => $agents[array_rand($agents)],
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Connection' => 'keep-alive',
            'Referer' => 'https://torob.com/', // if you're scraping internal links,
            'Accept-Encoding' => 'gzip, deflate, br',
        ];

        $cacheKey = $product->torob_id;

        if ($response = Cache::get($cacheKey)) {
            return $response;
        }

        /** @var Response $response */
        $url = env('CRAWLER_BASE_URL');
        $body = [
            'url' => $product->torob_id
        ];

        $response = Http::withHeaders($headers)->post($url, $body);
        if ($response->status() === \Symfony\Component\HttpFoundation\Response::HTTP_OK) {
            Cache::put($cacheKey, $response->json(), now()->addDay());
        } else {
            throw UnProcessableResponseException::make("torob-ban");
        }

        return $response->json();
    }

    public function sendAmazonRequest($url)
    {
        if (empty($headers)) {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:138.0) Gecko/20100101 Firefox/138.0',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate, br, zstd',
                'DNT' => '1',
                'Sec-GPC' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Priority' => 'u=0, i',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
            ];
        }

        $urlMd5 = md5($url);

//        if ($response = Cache::get($urlMd5)) {
//            return $response;
//        }

        /** @var Response $response */
        $response = Http::withHeaders($headers)->get($url);
        Cache::put($urlMd5, $response->body(), now()->addDay());

        return $response->body();
    }

    public static function getDecathlonData($url)
    {
        $cacheKey = md5($url);
        if ($response = Cache::get($cacheKey)) {
            return $response;
        }

        $response = Http::asJson()->post('zitazi-node:3000/seed', [
            'url' => $url
        ]);
        sleep(2);

        if ($response->successful() && $response->json('success')) {
            Cache::put($cacheKey, $response->json(), now()->addDay());
            /** @var Response $response */
            return $response->json();
        } else {
            throw new UnProcessableResponseException("error-in-decathlon-node:$url status:{$response->status()} error:{$response->body()}");
        }
    }

    public static function getTrendyolData($contentId, $merchantId = null)
    {
        $url = 'https://apigw.trendyol.com/discovery-storefront-trproductgw-service/api/product-detail/content';
        $params = http_build_query([
            'contentId' => $contentId,
            'merchantId' => $merchantId,
        ]);
        $fullUrl = $url . '?' . $params;

        $cacheKey = md5($fullUrl);
        if ($response = Cache::get($cacheKey)) {
            return $response;
        }

        $response = Http::asJson()->withHeaders([
            'accept-language' => 'en-GB,en;q=0.9,en-US;q=0.8,fa;q=0.7',
            'cache-control' => 'no-cache',
            'origin' => 'https://www.trendyol.com',
            'pragma' => 'no-cache',
            'priority' => 'u=1, i',
            'sec-ch-ua' => 'Not)A;Brand";v="8", "Chromium";v="138", "Google Chrome";v="138',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => 'Linux',
            'sec-fetch-dest' => 'empty',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-site' => 'same-site',
            'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
        ])->get($fullUrl);

        if ($response->successful()) {
            Cache::put($cacheKey, $response->json(), now()->addDay());
            /** @var Response $response */
            return $response->json();
        } else {
            throw new UnProcessableResponseException("error-in-trendyol-url:$url status:{$response->status()} error:{$response->body()}");
        }
    }

}
