<?php

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
            'arzdigital' => App\Services\CurrencyRate\ArzDigitalService::class,
            'navasan' => App\Services\CurrencyRate\NavasanService::class,
        ]
    ],

    'sync_enabled' => env('SYNC_ENABLED'),

    'zitazi' => [
        'security_key' => env('SECURITY_KEY'),
        'security_pass' => env('SECURITY_PASS'),
    ],
    'satreh' => [
        'sync_enabled' => env('SYNC_ENABLED'),
        'security_key' => env('SATRE_SECURITY_KEY'),
        'security_pass' => env('SATRE_SECURITY_PASS'),
    ]

];
