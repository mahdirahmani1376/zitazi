<?php

namespace App\Actions;

use App\Exceptions\UnProcessableResponseException;
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
        if ($response->status() === \Symfony\Component\HttpFoundation\Response::HTTP_OK) {
            Cache::put($urlMd5, $response->body(), now()->addDay());
        } else {
            throw UnProcessableResponseException::make("error-in-url-$url");
        }

        return $response->body();
    }
}
