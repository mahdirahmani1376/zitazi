<?php

namespace App\Actions;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SendHttpRequestAction
{
    private array $headers;

    public function __construct()
    {
        $this->headers = [
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.3',
        ];
    }

    public function __invoke($method, $url): Response
    {

//        $cacheKey = md5($url);

//        $cachedResponse = Cache::get($cacheKey);
//
//        if ($cachedResponse) {
//            // Reconstruct the Response object
//            return new Response(new \GuzzleHttp\Psr7\Response(200, [], $cachedResponse));
//        }

        /** @var Response $response */
        // Make the HTTP request
        $response = Http::withHeaders($this->headers)->$method($url);

//        if ($response->successful())
//        {
//            // Cache only the response body
//            Cache::put($cacheKey, $response->body(), now()->addDay());
//        }


        return $response;

    }
}
