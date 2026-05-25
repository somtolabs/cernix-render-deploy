<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Remita Fintech API Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials and base URL for the Remita payment gateway.
    | All values are read from the environment — never hardcoded.
    |
    | REMITA_PUBLIC_KEY  — identifies your merchant account (Authorization header)
    | REMITA_SECRET_KEY  — used to generate the HMAC token (never sent over wire)
    | REMITA_BASE_URL    — production or approved Remita endpoint
    |
    */

    'base_url'    => env('REMITA_BASE_URL', ''),
    'merchant_id' => env('REMITA_MERCHANT_ID', ''),
    'service_type_id' => env('REMITA_SERVICE_TYPE_ID', ''),
    'api_key'     => env('REMITA_API_KEY', ''),
    'public_key'  => env('REMITA_PUBLIC_KEY', ''),
    'secret_key'  => env('REMITA_SECRET_KEY', ''),
];
