<?php

$frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');
$extra = array_filter(array_map(
    static fn (string $u) => rtrim(trim($u), '/'),
    explode(',', (string) env('FRONTEND_URLS', ''))
));

$origins = array_values(array_unique(array_filter([
    $frontendUrl,
    ...$extra,
])));

// Origines locales uniquement hors production.
if (env('APP_ENV', 'production') !== 'production') {
    $origins = array_values(array_unique([
        ...$origins,
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:3001',
    ]));
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Auth = Bearer tokens (Next.js + mobile). supports_credentials reste false.
    | Définir FRONTEND_URL (+ FRONTEND_URLS, CSV) avec les origines HTTPS du web.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
