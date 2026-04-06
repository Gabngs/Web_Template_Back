<?php
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * KeycloakAuth – Valida tokens JWT emitidos por Keycloak.
 *
 * Flujo:
 *  1. Extrae el Bearer token del header Authorization
 *  2. Descarga (y cachea) las claves públicas JWKS de Keycloak
 *  3. Verifica la firma del JWT sin llamar a Keycloak en cada request
 *  4. Sincroniza el usuario en la DB local (crea o actualiza)
 *  5. Inyecta el usuario en auth()->user() para el resto de la app
 *
 * Uso en rutas:
 *  Route::middleware('keycloak')->group(...)
 *
 * NO requiere laravel/sanctum ni tabla personal_access_tokens.
 */
class KeycloakAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json(['message' => 'Token no proporcionado'], 401);
        }

        $payload = $this->validateToken($token);

        if (!$payload) {
            return response()->json(['message' => 'Token inválido o expirado'], 401);
        }

        // Sincronizar usuario en DB local
        $user = $this->syncUser($payload);

        if (!$user->is_active) {
            return response()->json(['message' => 'Usuario inactivo'], 403);
        }

        // Inyectar usuario en el guard de Laravel
        auth()->setUser($user);

        return $next($request);
    }

    // ─── Extraer token del header ─────────────────────────────────────────────

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    // ─── Validar JWT contra JWKS de Keycloak ─────────────────────────────────

    private function validateToken(string $token): ?array
    {
        try {
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                return null;
            }

            // Decodificar header para obtener kid (key ID)
            $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
            $kid    = $header['kid'] ?? null;

            // Obtener clave pública del JWKS (cacheada)
            $publicKey = $this->getPublicKey($kid);

            if (!$publicKey) {
                return null;
            }

            // Decodificar payload
            $payload = json_decode(
                base64_decode(strtr($parts[1], '-_', '+/')),
                true
            );

            // Verificar firma
            $data      = $parts[0] . '.' . $parts[1];
            $signature = base64_decode(strtr($parts[2], '-_', '+/'));

            $verified = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);

            if ($verified !== 1) {
                return null;
            }

            // Verificar expiración
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }

            // Verificar audience
            $audience = $payload['aud'] ?? [];
            $clientId = config('keycloak.client_id');
            if (is_string($audience)) {
                $audience = [$audience];
            }
            if (!in_array($clientId, $audience) && !in_array('account', $audience)) {
                Log::warning('Keycloak JWT: audience no coincide', ['aud' => $audience]);
            }

            return $payload;

        } catch (\Throwable $e) {
            Log::error('Keycloak JWT validation error: ' . $e->getMessage());
            return null;
        }
    }

    // ─── Obtener clave pública desde JWKS (con caché) ────────────────────────

    private function getPublicKey(?string $kid): mixed
    {
        $cacheKey = 'kc_jwks_' . ($kid ?? 'default');
        $ttl      = config('keycloak.jwks_cache_ttl', 300);

        return Cache::remember($cacheKey, $ttl, function () use ($kid) {
            $jwksUri  = config('keycloak.jwks_uri');
            $response = Http::timeout(5)->get($jwksUri);

            if (!$response->ok()) {
                Log::error('No se pudo obtener JWKS de Keycloak', ['uri' => $jwksUri]);
                return null;
            }

            $keys = $response->json('keys', []);

            foreach ($keys as $key) {
                if ($kid && ($key['kid'] ?? '') !== $kid) {
                    continue;
                }

                if (($key['kty'] ?? '') === 'RSA') {
                    return $this->rsaKeyFromJwk($key);
                }
            }

            return null;
        });
    }

    // ─── Convertir JWK RSA a clave pública OpenSSL ───────────────────────────

    private function rsaKeyFromJwk(array $jwk): mixed
    {
        $n = new \phpseclib3\Math\BigInteger(
            base64_decode(strtr($jwk['n'], '-_', '+/')), 256
        );
        $e = new \phpseclib3\Math\BigInteger(
            base64_decode(strtr($jwk['e'], '-_', '+/')), 256
        );

        $rsa = new \phpseclib3\Crypt\RSA();
        $key = \phpseclib3\Crypt\RSA::loadPublicKey(['n' => $n, 'e' => $e]);

        return openssl_pkey_get_public($key->toString('PKCS8'));
    }

    // ─── Sincronizar usuario en DB local ─────────────────────────────────────

    private function syncUser(array $payload): User
    {
        $keycloakId = $payload['sub'];         // UUID del usuario en Keycloak
        $email      = $payload['email'] ?? $keycloakId . '@keycloak.local';
        $name       = trim(($payload['given_name'] ?? '') . ' ' . ($payload['family_name'] ?? ''))
                      ?: ($payload['preferred_username'] ?? 'Usuario');

        // Roles del realm (realm_access.roles)
        $roles = $payload['realm_access']['roles'] ?? [];
        // Filtrar roles internos de Keycloak
        $roles = array_filter($roles, fn($r) => !in_array($r, [
            'offline_access', 'uma_authorization', 'default-roles-' . config('keycloak.realm'),
        ]));

        $user = User::updateOrCreate(
            ['keycloak_id' => $keycloakId],
            [
                'name'     => $name,
                'email'    => $email,
                'password' => bcrypt(str()->random(32)), // password local irrelevante
            ]
        );

        // Sincronizar roles con la tabla local
        $roleIds = \App\Models\Role::whereIn('name', $roles)->pluck('id');
        $user->roles()->sync($roleIds);

        // Actualizar rol_id principal (primer rol)
        if ($roleIds->isNotEmpty()) {
            $user->update(['rol_id' => $roleIds->first()]);
        }

        return $user;
    }
}
