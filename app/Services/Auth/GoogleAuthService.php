<?php
namespace App\Services\Auth;

use App\Exceptions\GoogleTokenException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GoogleAuthService
 *
 * Verifica ID Tokens emitidos por Google Identity Services (GSI) y gestiona
 * el self-provisioning de usuarios en la base de datos local.
 *
 * Flujo:
 *  1. El frontend obtiene un `credential` (ID Token) usando la librería GSI de Google.
 *  2. El frontend envía ese ID Token al endpoint POST /api/auth/google.
 *  3. Este servicio verifica la firma del token contra las claves públicas JWKS de Google.
 *  4. Valida `aud` (debe coincidir con GOOGLE_CLIENT_ID) y `iss` (cuentas de Google).
 *  5. Busca o crea el usuario local usando el campo `sub` (identificador único de Google).
 */
class GoogleAuthService
{
    // ─── API pública ──────────────────────────────────────────────────────────

    /**
     * Verifica el ID Token de Google y devuelve el payload si es válido.
     * Devuelve null si la verificación falla (firma inválida, expirado, audience incorrecto…).
     */
    public function verifyIdToken(string $idToken): ?array
    {
        if (empty(config('google.client_id'))) {
            Log::error('Google Auth: GOOGLE_CLIENT_ID no está configurado en .env');
            return null;
        }

        try {
            return $this->decodeAndValidate($idToken);
        } catch (GoogleTokenException $e) {
            Log::warning("Google JWT: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Busca o crea el usuario local a partir del payload del ID Token.
     *
     * Prioridad de búsqueda:
     *  1. Por google_sub (identificador único e inmutable de Google)
     *  2. Por email (el usuario puede haber sido creado manualmente antes)
     *  3. Self-provisioning si no existe
     */
    public function findOrCreateUser(array $payload): User
    {
        $googleSub     = $payload['sub'];
        $email         = $payload['email'] ?? null;
        $name          = $payload['name'] ?? ($payload['given_name'] ?? 'Usuario');
        $userAvatarUrl = $payload['picture'] ?? null;

        $user = User::where('google_sub', $googleSub)->first();

        if ($user) {
            $user->update(['user_avatar_url' => $userAvatarUrl]);
            return $user;
        }

        if ($email) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            $user->update(['google_sub' => $googleSub, 'user_avatar_url' => $userAvatarUrl]);
            return $user;
        }

        return User::create([
            'name'            => $name,
            'email'           => $email ?? "{$googleSub}@google.local",
            'password'        => bcrypt(Str::random(32)),
            'google_sub'      => $googleSub,
            'user_avatar_url' => $userAvatarUrl,
            'is_active'       => true,
            // password_set_at queda null → frontend detecta y muestra modal de contraseña
        ]);
    }

    // ─── Validación interna ───────────────────────────────────────────────────

    /**
     * Decodifica y valida el JWT. Lanza RuntimeException ante cualquier fallo.
     */
    private function decodeAndValidate(string $idToken): array
    {
        $parts = explode('.', $idToken);

        if (\count($parts) !== 3) {
            throw new GoogleTokenException('Formato de token inválido (no es un JWT)');
        }

        $header  = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        if (!$header || !$payload) {
            throw new GoogleTokenException('No se pudo decodificar el token');
        }

        $publicKey = $this->getPublicKey($header['kid'] ?? null);

        if (!$publicKey) {
            throw new GoogleTokenException('Clave pública de Google no encontrada (kid no coincide)');
        }

        $data      = "{$parts[0]}.{$parts[1]}";
        $signature = base64_decode(strtr($parts[2], '-_', '+/'));

        if (openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new GoogleTokenException('Firma del token inválida');
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw new GoogleTokenException('Token expirado');
        }

        if (!\in_array($payload['iss'] ?? '', config('google.valid_issuers', []), true)) {
            throw new GoogleTokenException("Issuer inválido: {$payload['iss']}");
        }

        $clientId  = config('google.client_id');
        $aud       = $payload['aud'] ?? '';
        $audiences = \is_array($aud) ? $aud : [$aud];

        if (!\in_array($clientId, $audiences, true)) {
            throw new GoogleTokenException('Audience (aud) del token no coincide con GOOGLE_CLIENT_ID');
        }

        return $payload;
    }

    // ─── JWKS: obtener clave pública cacheada ─────────────────────────────────

    private function getPublicKey(?string $kid): mixed
    {
        $cacheKey = "google_jwks_{$kid}";
        $ttl      = config('google.jwks_cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, fn() => $this->fetchPublicKey($kid));
    }

    private function fetchPublicKey(?string $kid): mixed
    {
        $response = Http::timeout(5)->get(config('google.jwks_uri'));

        if (!$response->ok()) {
            Log::error('No se pudieron obtener las claves JWKS de Google');
            return null;
        }

        $result = null;

        foreach ($response->json('keys', []) as $key) {
            if ($kid && ($key['kid'] ?? '') !== $kid) {
                continue;
            }

            if (!empty($key['x5c'][0])) {
                $pem    = "-----BEGIN CERTIFICATE-----\n"
                        . chunk_split($key['x5c'][0], 64, "\n")
                        . "-----END CERTIFICATE-----";
                $result = openssl_pkey_get_public($pem);
                break;
            }

            if (($key['kty'] ?? '') === 'RSA' && !empty($key['n']) && !empty($key['e'])) {
                $result = $this->rsaKeyFromComponents($key['n'], $key['e']);
                break;
            }
        }

        return $result;
    }

    // ─── Construcción manual de clave RSA desde n/e (fallback sin dependencias) ─

    /**
     * Construye un recurso OpenSSL de clave pública RSA desde los componentes
     * base64url n (modulus) y e (exponent) del JWK, sin librerías externas.
     */
    private function rsaKeyFromComponents(string $n, string $e): mixed
    {
        $nBin = base64_decode(strtr($n, '-_', '+/'));
        $eBin = base64_decode(strtr($e, '-_', '+/'));

        $encodeLen = static function (int $len): string {
            if ($len < 128) {
                return \chr($len);
            }
            $bytes = '';
            $tmp   = $len;
            while ($tmp > 0) {
                $bytes = \chr($tmp & 0xFF) . $bytes;
                $tmp >>= 8;
            }
            return \chr(0x80 | \strlen($bytes)) . $bytes;
        };

        $encodeInt = static function (string $bin) use ($encodeLen): string {
            $bin = (\ord($bin[0]) >= 0x80) ? "\x00{$bin}" : $bin;
            return "\x02" . $encodeLen(\strlen($bin)) . $bin;
        };

        $modulus  = $encodeInt($nBin);
        $exponent = $encodeInt($eBin);
        $sequence = "\x30" . $encodeLen(\strlen($modulus . $exponent)) . $modulus . $exponent;

        $rsaOid    = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $bitString = "\x03" . $encodeLen(\strlen($sequence) + 1) . "\x00" . $sequence;
        $spki      = "\x30" . $encodeLen(\strlen($rsaOid . $bitString)) . $rsaOid . $bitString;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($spki), 64, "\n")
             . "-----END PUBLIC KEY-----";

        return openssl_pkey_get_public($pem);
    }
}
