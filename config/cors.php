<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */
    'paths' => ['api/*', 'auth/broadcasting'],
    'allowed_methods' => ['*'],
    // Restrict to known origins in production. Set CORS_ALLOWED_ORIGINS in .env.
    // Default '*' is only safe in closed on-prem networks; still prefer explicit allowlist.
    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', '*'))),
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    // Must be false when allowed_origins is ['*'].
    // The CORS spec forbids Access-Control-Allow-Origin: * with credentials.
    // Tablet PWA uses Bearer token auth — no cookies needed.
    'supports_credentials' => false,

];
