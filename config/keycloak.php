<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Keycloak Identity Provider
    |--------------------------------------------------------------------------
    | Configuración del servidor Keycloak. Todos los valores se leen desde .env
    | para que sea fácil cambiar entre dev / staging / producción.
    |
    | URL base:  KC_URL (ej: http://keycloak:8080 dentro de Docker)
    | Realm:     KC_REALM (ej: web-template)
    | Client ID: KC_CLIENT_ID (ej: web-backend)
    | Secret:    KC_CLIENT_SECRET
    */

    'url'           => env('KC_URL', 'http://keycloak:8080'),
    'realm'         => env('KC_REALM', 'web-template'),
    'client_id'     => env('KC_CLIENT_ID', 'web-backend'),
    'client_secret' => env('KC_CLIENT_SECRET', 'change-me-in-production'),

    /*
    | URLs derivadas automáticamente del realm
    */
    'jwks_uri'      => env('KC_URL', 'http://keycloak:8080')
                       . '/realms/' . env('KC_REALM', 'web-template')
                       . '/protocol/openid-connect/certs',

    'token_uri'     => env('KC_URL', 'http://keycloak:8080')
                       . '/realms/' . env('KC_REALM', 'web-template')
                       . '/protocol/openid-connect/token',

    'logout_uri'    => env('KC_URL', 'http://keycloak:8080')
                       . '/realms/' . env('KC_REALM', 'web-template')
                       . '/protocol/openid-connect/logout',

    /*
    | Cache en segundos para las claves públicas JWKS (evita llamadas repetidas)
    */
    'jwks_cache_ttl' => (int) env('KC_JWKS_CACHE_TTL', 300),

    /*
    | Claim del JWT donde vienen los roles del realm
    */
    'roles_claim'   => 'realm_access.roles',
];
