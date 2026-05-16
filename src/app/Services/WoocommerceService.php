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
        $baseURl = "https://zitazi-4fcf91dea6-iran.apps.ir-central1.arvancaas.ir/wp-json/wc/v3";
        $securityPass = null;
        $securityKey = null;
        $appendUrl = null;

        if ($source === Product::ZITAZI) {
            $securityKey = env('SECURITY_KEY');
            $securityPass = env('SECURITY_PASS');
            $appendUrl = 'zitazi';
        } else if ($source === Product::SATRE) {
            $securityKey = env('SATRE_SECURITY_KEY');
            $securityPass = env('SATRE_SECURITY_PASS');
            $appendUrl = 'satreh';
        }

        $fullUrl = "{$baseURl}/$appendUrl/{$url}";


        /** @var Response $response */
        $response = Http::withBasicAuth($securityKey, $securityPass)
            ->acceptJson()
            ->$method($fullUrl, $body);

        return $response;

    }

    public static function getClient(string $source = Product::ZITAZI): Client
    {
        $baseURl = env('BASE_URL');
        $securityKey = env('SECURITY_KEY');
        $securityPass = env('SECURITY_PASS');
        if ($source === Product::SATRE) {
            $baseURl = env('SATRE_BASE_URL');
            $securityKey = env('SATRE_SECURITY_KEY');
            $securityPass = env('SATRE_SECURITY_PASS');
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
