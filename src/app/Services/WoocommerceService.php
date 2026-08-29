<?php

namespace App\Services;

use App\Models\Product;
use Automattic\WooCommerce\Client;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WoocommerceService
{

    public static function sendRequest($url, $body = [], $method = 'get', string $source = Product::ZITAZI): Response
    {
        $securityPass = null;
        $securityKey = null;
        $baseURl = "https://zitazi.com";

        if ($source === Product::ZITAZI) {
            $securityKey = config('services.zitazi.security_key');
            $securityPass = config('services.zitazi.security_pass');
        } else if ($source === Product::SATRE) {
            $securityKey = config('services.satreh.security_key');
            $securityPass = config('services.satreh.security_key');
            $baseURl = 'https://proxy.mahdi-rahmani.ir/satreh';
        }

        $fullUrl = "{$baseURl}/wp-json/wc/v3/{$url}";

        /** @var Response $response */
        $response = Http::withBasicAuth($securityKey, $securityPass)
            ->acceptJson()
            ->$method($fullUrl, $body);

        return $response;

    }

    public static function getClient(string $source = Product::ZITAZI): Client
    {
        $securityPass = null;
        $securityKey = null;
        $baseURl = "https://zitazi.com";

        if ($source === Product::ZITAZI) {
            $securityKey = config('services.zitazi.security_key');
            $securityPass = config('services.zitazi.security_pass');
        } else if ($source === Product::SATRE) {
            $securityKey = config('services.satreh.security_key');
            $securityPass = config('services.satreh.security_key');
            $baseURl = 'https://proxy.mahdi-rahmani.ir/satreh';
        }

        return new Client(
            $baseURl,
            $securityKey,
            $securityPass,
            [
                'wp_api' => true,
                'version' => 'wc/v3',
            ]
        );
    }
}
