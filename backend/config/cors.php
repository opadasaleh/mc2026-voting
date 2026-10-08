<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The Next.js frontend (voting page + TV screen) calls the API from the
    | browser on another origin. Only the origins in FRONTEND_URLS (comma-
    | separated, e.g. "http://localhost:3000,http://192.168.0.146:3000") may
    | do so. The API uses bearer tokens, never cookies, so credentials stay off.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        fn (string $url) => rtrim(trim($url), '/'),
        explode(',', (string) env('FRONTEND_URLS', 'http://localhost:3000')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type'],

    // Lets the frontend show "try again in N seconds" from 429 responses.
    'exposed_headers' => ['Retry-After'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
