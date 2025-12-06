<?php

namespace App\Services;

use App\Models\Product;
use Automattic\WooCommerce\Client;

class WoocommerceService
{
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
