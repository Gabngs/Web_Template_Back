<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google OAuth 2.0 / Identity Services
    |--------------------------------------------------------------------------
    | GOOGLE_CLIENT_ID  : El Client ID que Google emite para esta aplicación.
    |                     Se usa para validar el campo "aud" del ID Token.
    |
    | Obtener en: https://console.cloud.google.com/ → APIs & Services → Credentials
    |
    | JWKS público de Google (rotación automática — se cachea localmente):
    |   https://www.googleapis.com/oauth2/v3/certs
    */

    'client_id' => env('GOOGLE_CLIENT_ID', ''),

    // Emisores válidos de tokens de Google
    'valid_issuers' => [
        'https://accounts.google.com',
        'accounts.google.com',
    ],

    // URL de las claves públicas de Google (JWKS)
    'jwks_uri' => 'https://www.googleapis.com/oauth2/v3/certs',

    // Segundos de caché para las claves públicas JWKS (1 hora por defecto)
    'jwks_cache_ttl' => (int) env('GOOGLE_JWKS_CACHE_TTL', 3600),
];
