<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Provider
    |--------------------------------------------------------------------------
    */
    'default' => env('TREASURER_PROVIDER', 'gopay'),

    /*
    |--------------------------------------------------------------------------
    | Debug Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, gateway drivers forward outbound HTTP requests and
    | responses to any logger the host application binds (for example a
    | Telescope-aware GoPay\Http\Log\Logger implementation). Leave disabled
    | in production unless you are actively diagnosing gateway traffic, as
    | full payloads may include personally identifiable information.
    |
    */
    'debug' => filter_var(env('TREASURER_DEBUG', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Shared Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'environment' => env('TREASURER_ENV', 'sandbox'),
        'currency' => env('TREASURER_CURRENCY', 'CZK'),
        'language' => env('TREASURER_LANGUAGE', 'CS'),
        'timeout' => (int) env('TREASURER_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'gopay' => [
            'environment' => env('GOPAY_ENV', 'sandbox'),
            'credentials' => [
                'goid' => env('GOPAY_ID'),
                'client_id' => env('GOPAY_CLIENT_ID'),
                'client_secret' => env('GOPAY_CLIENT_SECRET'),
            ],
            'urls' => [
                'return' => env('GOPAY_RETURN_URL'),
                'notification' => env('GOPAY_NOTIFICATION_URL'),
            ],
            'webhooks' => [
                'token' => env('GOPAY_WEBHOOK_TOKEN'),
                'secret' => env('GOPAY_WEBHOOK_SECRET'),
            ],
            'options' => [
                'currency' => env('GOPAY_CURRENCY'),
                'language' => env('GOPAY_LANGUAGE'),
                'timeout' => env('GOPAY_TIMEOUT'),
            ],
        ],
        'comgate' => [
            'environment' => env('COMGATE_ENV', 'sandbox'),
            'credentials' => [
                'merchant' => env('COMGATE_MERCHANT'),
                'secret' => env('COMGATE_SECRET'),
            ],
            'urls' => [
                'return' => env('COMGATE_RETURN_URL'),
                'notification' => env('COMGATE_NOTIFICATION_URL'),
                'api' => env('COMGATE_API_URL'),
            ],
            'webhooks' => [
                'token' => env('COMGATE_WEBHOOK_TOKEN'),
                'secret' => env('COMGATE_WEBHOOK_SECRET'),
            ],
            'options' => [
                'currency' => env('COMGATE_CURRENCY', 'CZK'),
                'language' => env('COMGATE_LANGUAGE'),
                'timeout' => env('COMGATE_TIMEOUT'),
            ],
        ],
    ],
];
