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

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Defaults to '*' for local/dev (token API — the bearer token is a client-set
    // header, not a cookie, so '*' is not a credential leak). In production set
    // CORS_ALLOWED_ORIGINS to a comma-separated list of the real web/mobile origins.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
    ))) ?: ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Keep false for token auth. Only enable for cookie-based (Sanctum SPA) auth,
    // and then CORS_ALLOWED_ORIGINS must list explicit origins (never '*').
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),
];
