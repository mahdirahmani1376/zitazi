<?php

namespace App\Actions;

use Illuminate\Http\Client\Response;
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
}
