<?php

namespace App\Actions;

use App\Exceptions\UnProcessableResponseException;
use App\Models\Product;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SendHttpRequestAction
{
    public function __invoke($method, $url, $headers = []): Response
    {
        if (empty($headers)) {
            $headers = [
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
            ];
        }

        /** @var Response $response */
        $response = Http::withHeaders($headers)->$method($url);

        return $response;

    }

    public function sendWithCache($method, $url)
    {
        if (empty($headers)) {
            $headers = [
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
            ];
        }

        $urlMd5 = md5($url);

        if ($response = Cache::get($urlMd5)) {
            return $response;
        }

        /** @var Response $response */
        $response = Http::withHeaders($headers)->$method($url);
        Cache::put($urlMd5, $response->body(), now()->addDay());

        return $response->body();
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
}
