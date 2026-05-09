<?php

namespace App\Services;

use App\Models\Product;
use Automattic\WooCommerce\Client;

class WoocommerceService
{
    public static function getClient(string $source = Product::ZITAZI): Client
    {
        $baseURl = env('BASE_URL');
        $securityKey = env('ck_e15335b8a32a1d69e70d58945979198de2de443e');
        $securityPass = env('cs_5e59935a2584ea5c5d9aeb84ceb5d429f3df477f');
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
