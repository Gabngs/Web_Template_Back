<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:4200')),

    // Fuera de producción, matchea cualquier puerto en localhost — evita
    // tener que actualizar CORS_ALLOWED_ORIGINS cada vez que el frontend
    // (Angular, IIS Express, etc.) arranca en un puerto distinto.
    'allowed_origins_patterns' => array_filter(explode(',', env(
        'CORS_ALLOWED_ORIGIN_PATTERNS',
        env('APP_ENV', 'production') !== 'production' ? '#^https?://localhost:\d+$#' : ''
    ))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Toda la auth va por header Authorization: Bearer, nunca por cookies —
    // el frontend Angular no debe usar withCredentials: true.
    'supports_credentials' => false,
];
