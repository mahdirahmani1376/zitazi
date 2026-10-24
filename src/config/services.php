<?php

use App\Services\CurrencyRate\ArzDigitalService;
use App\Services\CurrencyRate\NavasanService;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'currency-rate' => [
        'driver' => env('RATE_DRIVER', 'arzdigital'),
        'drivers' => [
            'arzdigital' => ArzDigitalService::class,
            'navasan' => NavasanService::class,
        ],
    ],

    'zitazi_sync_enabled' => env('ZITAZI_SYNC_ENABLED', true),
    'satreh_sync_enabled' => env('SATREH_SYNC_ENABLED', true),

    'zitazi' => [
        'security_key' => env('SECURITY_KEY'),
        'security_pass' => env('SECURITY_PASS'),
        'base_url' => env('ZITAZI_BASE_URL'),
        'torob_shop_id' => env('TOROB_SHOP_ID'),
    ],
    'satreh' => [
        'security_key' => env('SATRE_SECURITY_KEY'),
        'security_pass' => env('SATRE_SECURITY_PASS'),
        'base_url' => env('SATRE_BASE_URL'),
    ],

    'scrapeops' => [
        'key' => env('SCRAPE_OPS_KEY'),
        'max_requests' => (int) env('SCRAPE_OPS_MAX_REQUESTS', 800),
        'cache_ttl_days' => (int) env('SCRAPE_OPS_CACHE_TTL_DAYS', 30),
        'sleep_seconds' => (int) env('SCRAPE_OPS_SLEEP_SECONDS', 7),
    ],

];
