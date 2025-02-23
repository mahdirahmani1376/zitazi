<?php

namespace App\Services;

use Automattic\WooCommerce\Client;

class WoocommerceService
{
    public static function getClient(): Client
    {
        return new Client(
            env('BASE_URL'),
            env('SECURITY_KEY'),
            env('SECURITY_PASS'),
            [
                'wp_api' => true,
                'version' => 'wc/v3'
            ]
        );
    }
}
