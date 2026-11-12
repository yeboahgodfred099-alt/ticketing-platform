<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paystack Configuration
    |--------------------------------------------------------------------------
    |
    | Secret key is strictly server-side. It must NEVER be exposed to Next.js,
    | client bundles, or unmasked logs.
    |
    */

    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
    'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),

    'currency' => 'GHS',
    'timeout' => 15, // seconds for API requests

    'providers' => [
        'mtn' => 'MTN Mobile Money',
        'atl' => 'AirtelTigo Money',
        'vod' => 'Telecel Cash',
    ],
];

