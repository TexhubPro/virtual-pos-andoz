<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Currently only "production" is defined by the protocol. Use `base_url`
    | below to point at a staging instance provided by the ОФД operator.
    |
    */
    'environment' => env('VIRTUAL_POS_ENVIRONMENT', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Token
    |--------------------------------------------------------------------------
    |
    | Token issued by the ОФД operator. Sent in the Authorization header.
    |
    */
    'token' => env('VIRTUAL_POS_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Authorization scheme prefix (optional)
    |--------------------------------------------------------------------------
    |
    | Leave null to send the raw token. Set e.g. "Bearer" if your operator
    | expects "Authorization: Bearer <token>".
    |
    */
    'auth_prefix' => env('VIRTUAL_POS_AUTH_PREFIX'),

    /*
    |--------------------------------------------------------------------------
    | Base URL override
    |--------------------------------------------------------------------------
    |
    | Defaults to https://vkassa-api.ofd.tj when empty.
    |
    */
    'base_url' => env('VIRTUAL_POS_BASE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | HTTP timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('VIRTUAL_POS_TIMEOUT', 30),
];
