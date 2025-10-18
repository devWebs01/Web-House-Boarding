<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Midtrans Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Midtrans payment gateway integration.
    |
    */

    'server_key' => env('MIDTRANS_SERVER_KEY'),

    'client_key' => env('MIDTRANS_CLIENT_KEY'),

    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

    // Additional configuration for better error handling
    'append_notif_url' => env('MIDTRANS_APPEND_NOTIF_URL'),
    'override_notif_url' => env('MIDTRANS_OVERRIDE_NOTIF_URL'),

    'is_sanitized' => true,

    'is_3ds' => true,

];
