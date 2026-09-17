<?php

namespace App\Services\Auth;

use App\Mail\PasswordReseteadaMail;
use App\Models\dbsiaw\SiawContentPermisos;
use App\Models\dbsiaw\SiawMenus;
use App\Models\dbsiaw\SiawPermisoRol;
use App\Models\dbsiaw\SiawTokensAcceso;
use App\Models\dbsiaw\SiawUsuarios;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const MAX_SESSIONS      = 3;
    private const MAX_ATTEMPTS      = 10;
    private const MAX_IP_ATTEMPTS   = 20;
    private const BLOCK_MINUTES     = 30;
    private const IP_BLOCK_MINUTES  = 60;
    private const CHALLENGE_TTL     = 30;
    private const PASSWORD_MIN_LEN  = 8;

    private const DUMMY_HASH = '$2y$12$G16K4kX3VudzcG.foNTaCey3oPsykv5ouoXdxxWSqHxPe.WuaaKq2';

    // ── Challenge-Response ────────────────────────────────────────────────

    public function challenge(): array
    {
        $nonce = Str::uuid()->toString();
        cache()->put("auth_nonce_{$nonce}", true, now()->addSeconds(self::CHALLENGE_TTL));

        return [
            'message' => 'Challenge generado',
            'data'    => [
                'nonce'     => $nonce,
                'expira_en' => now()->addSeconds(self::CHALLENGE_TTL)->toIso8601String(),
            ],
        ];
    }

    // ── Login ─────────────────────────────────────────────────────────────

    public function login(array $data): array
    {
        $this->checkIpBlock();
        $this->checkEmailBlock($data['email']);
        $this->validateNonce($data['nonce']);

        $password = $this->decryptPassword($data['password']);

        // El campo "email" del request acepta email o codigo (5 dígitos, ej: 00001)
        $usuario = SiawUsuarios::where('activo', true)
            ->where(function ($query) use ($data) {
                $query->where('email', $data['email'])
                    ->orWhere('codigo', $data['email']);
            })
            ->first();

        $passwordOk = Hash::check($password, $usuario->password ?? self::DUMMY_HASH);

        if (!$usuario || !$passwordOk) {
            $this->trackFailedAttempt($data['email']);
            $this->audit('login_fail', $data['email'], ['ip' => request()->ip()]);
            throw new AuthenticationException('Credenciales incorrectas.');
        }

        $this->clearFailedAttempts($data['email']);
        $this->clearIpAttempts();
        $this->enforceSesionLimit($usuario);

        $expiraEn    = now()->addHours(6);
        $device      = $data['device'] ?? 'web';
        $sessionKey  = strtoupper(Str::random(6)) . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $fingerprint = hash('sha256', request()->userAgent() ?? '');
        $token       = $usuario->createToken("auth_{$device}", ['*'], $expiraEn);

        $token->accessToken->update([
            'session_key' => $sessionKey,
            'fingerprint' => $fingerprint,
        ]);
        $usuario->update(['ultimo_acceso_en' => now()]);

        $this->audit('login_ok', $usuario->email, ['device' => $device]);

        return [
            'message' => 'Login exitoso',
            'data'    => [
                'token'                 => $sessionKey . '@' . $token->plainTextToken,
                'session_key'           => $sessionKey,
                'expira_en'             => $expiraEn->format('Y-m-d H:i:s'),
                'expira_en_iso'         => $expiraEn->toIso8601String(),
                'debe_cambiar_password' => $usuario->debe_cambiar_password,
                'usuario'               => [
                    'id'        => $usuario->id,
                    'nombre'    => $usuario->nombre,
                    'apellidos' => $usuario->apellidos,
                    'email'     => $usuario->email,
                ],
            ],
        ];
    }

    // ── Sesiones ──────────────────────────────────────────────────────────

    public function sessions(SiawUsuarios $usuario): array
    {
        $currentTokenId = $usuario->currentAccessToken()?->id;

        $sesiones = $usuario->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(SiawTokensAcceso $t) => [
                'id'          => $t->id,
                'dispositivo' => str_replace('auth_', '', $t->nombre),
                'activa'      => $t->id === $currentTokenId,
                'ultimo_uso'  => $t->ultimo_uso_en?->format('Y-m-d H:i:s'),
                'expira_en'   => $t->expira_en?->format('Y-m-d H:i:s'),
                'creado_en'   => $t->created_at->format('Y-m-d H:i:s'),
            ]);

        return [
            'message' => 'Sesiones obtenidas correctamente.',
            'data'    => $sesiones,
        ];
    }

    public function revokeSession(SiawUsuarios $usuario, int $tokenId): array
    {
        $deleted = $usuario->tokens()->where('id', $tokenId)->delete();

        if ($deleted) {
            $this->audit('session_revoked', $usuario->email, ['token_id' => $tokenId]);
        }

        return [
            'message' => $deleted ? 'Sesión revocada.' : 'Sesión no encontrada.',
        ];
    }

    public function revokeAllSessions(SiawUsuarios $usuario): array
    {
        $usuario->tokens()->delete();
        $this->audit('all_sessions_revoked', $usuario->email);

        return [
            'message' => 'Todas las sesiones han sido cerradas.',
        ];
    }

    // ── Permisos ──────────────────────────────────────────────────────────

    public function permisos(SiawUsuarios $usuario): array
    {
        $paquete = $this->armarPaqueteSesion($usuario);

        return [
            'message' => $paquete['rol'] === null ? 'El usuario no tiene rol asignado.' : 'Permisos obtenidos correctamente.',
            'data'    => $paquete,
        ];
    }


    public function armarPaqueteSesion(SiawUsuarios $usuario): array
    {
        $rol = $usuario->rol;

        if (!$rol) {
            return ['rol' => null, 'permisos' => [], 'menus' => []];
        }


        $esSuper    = $rol->slug === 'isSuperUser';
        $permisoIds = collect();

        if ($esSuper) {
            $permisos = SiawContentPermisos::pluck('codename')->filter()->values();
        } else {
            $permisoIds = SiawPermisoRol::where('rol_id', $rol->pkid)->pluck('permiso_id');

            $permisos = SiawContentPermisos::whereIn('pkid', $permisoIds)
                ->pluck('codename')
                ->filter()
                ->values();
        }


        $menus = SiawMenus::where('activo', true)
            ->when(!$esSuper, function ($q) use ($permisoIds) {
                $q->where(function ($qq) use ($permisoIds) {
                    $qq->whereNull('ruta')
                        ->orWhereHas('permisos', fn ($qqq) => $qqq->whereIn('permiso_id', $permisoIds));
                });
            })
            ->with(['parent:pkid,id', 'sistema:pkid,id'])
            ->orderBy('orden')
            ->get(['id', 'sistema_id', 'parent_id', 'titulo', 'descripcion', 'ruta', 'nombre_icon', 'orden', 'dashboard']);


        $jerarquia = SiawMenus::get(['pkid', 'parent_id', 'activo'])->keyBy('pkid');

        $ancestroInactivo = function ($parentPkid) use ($jerarquia): bool {
            while ($parentPkid !== null) {
                $padre = $jerarquia->get($parentPkid);
                if ($padre === null || ! $padre->activo) {
                    return true;
                }
                $parentPkid = $padre->parent_id;
            }
            return false;
        };

        $menus = $menus
            ->reject(fn (SiawMenus $menu) => $ancestroInactivo($menu->parent_id))

            ->map(fn (SiawMenus $menu) => [
                'id'          => $menu->id,
                'sistema_id'  => $menu->sistema?->id,
                'parent_id'   => $menu->parent?->id,
                'titulo'      => $menu->titulo,
                'descripcion' => $menu->descripcion,
                'ruta'        => $menu->ruta,
                'nombre_icon' => $menu->nombre_icon,
                'orden'       => $menu->orden,
                'dashboard'   => (bool) $menu->dashboard,
            ])
            ->values();

        return [
            'rol'      => ['id' => $rol->id, 'name' => $rol->name, 'slug' => $rol->slug],
            'permisos' => $permisos,
            'menus'    => $menus,
        ];
    }

    // ── Logout ────────────────────────────────────────────────────────────

    public function logout(SiawUsuarios $usuario): array
    {
        /** @var SiawTokensAcceso $token */
        $token = $usuario->currentAccessToken();
        $token->delete();
        $this->audit('logout', $usuario->email);

        return [
            'message' => 'Sesión cerrada.',
        ];
    }

    // ── Cambio de contraseña (self-service) ──────────────────────────────

    /**
     * El propio usuario cambia su contraseña — cubre tanto el cambio forzado
     * (debe_cambiar_password=true tras un reset o alta con password inicial)
     * como el cambio voluntario. Ambas contraseñas llegan RSA+base64, igual
     * que en el login.
     */
    public function cambiarPassword(SiawUsuarios $usuario, string $actualEncrypted, string $nuevaEncrypted): array
    {
        $actual = $this->decryptPassword($actualEncrypted);
        $nueva  = $this->decryptPassword($nuevaEncrypted);

        if (!Hash::check($actual, $usuario->password)) {
            $this->audit('password_change_fail', $usuario->email);
            throw new AuthenticationException('La contraseña actual es incorrecta.');
        }

        if (strlen($nueva) < self::PASSWORD_MIN_LEN) {
            throw ValidationException::withMessages([
                'password_nueva' => ['La nueva contraseña debe tener al menos ' . self::PASSWORD_MIN_LEN . ' caracteres.'],
            ]);
        }

        if (Hash::check($nueva, $usuario->password)) {
            throw ValidationException::withMessages([
                'password_nueva' => ['La nueva contraseña debe ser diferente a la actual.'],
            ]);
        }

        $usuario->update([
            'password'              => Hash::make($nueva),
            'debe_cambiar_password' => false,
        ]);

        // Cambiar la contraseña revoca el resto de sesiones (posible robo de
        // cuenta) pero conserva la sesión actual para no cortar al usuario
        // en medio del flujo.
        $currentTokenId = $usuario->currentAccessToken()?->id;
        $usuario->tokens()->where('id', '!=', $currentTokenId)->delete();

        $this->audit('password_changed', $usuario->email);

        return [
            'message' => 'Contraseña actualizada correctamente.',
        ];
    }

    // ── Reset / Desbloqueo ────────────────────────────────────────────────

    public function resetPassword(SiawUsuarios $usuario): array
    {
        $temporal = Str::random(10) . random_int(10, 99);

        $usuario->update([
            'password'              => Hash::make($temporal),
            'debe_cambiar_password' => 1,
        ]);

        $this->clearFailedAttempts($usuario->email);
        $this->audit('password_reset', $usuario->email);

        // La temporal nunca viaja en la respuesta HTTP (queda en logs de
        // gateway/APM) — se entrega solo por correo, canal fuera de banda.
        Mail::to($usuario->email)->send(new PasswordReseteadaMail($usuario, $temporal));

        return [
            'message' => 'Contraseña restablecida. Se envió la nueva contraseña temporal al correo del usuario.',
        ];
    }

    public function unblockUser(string $email): array
    {
        $this->clearFailedAttempts($email);
        $this->audit('user_unblocked', $email);

        return [
            'message' => "Usuario {$email} desbloqueado.",
        ];
    }

    // ── Helpers de slot RSA ───────────────────────────────────────────────

    public static function currentSlot(): int
    {
        return (int) floor(time() / 7200);
    }

    public static function publicKeyPath(): string
    {
        return storage_path('keys/public_' . self::currentSlot() . '.pem');
    }

    // ── Privados ──────────────────────────────────────────────────────────

    private function validateNonce(string $nonce): void
    {
        $key = "auth_nonce_{$nonce}";

        if (!cache()->has($key)) {
            $this->audit('invalid_nonce', '', ['nonce' => $nonce]);
            throw new AuthenticationException('Challenge inválido o expirado. Solicite uno nuevo en GET /auth/challenge.');
        }

        cache()->forget($key);
    }

    private function decryptPassword(string $encrypted): string
    {
        $slot      = self::currentSlot();
        $keysExist = false;

        foreach ([$slot, $slot - 1] as $s) {
            $path = storage_path("keys/private_{$s}.pem");

            if (!file_exists($path)) {
                continue;
            }
            $keysExist = true;

            $decrypted = null;
            openssl_private_decrypt(
                base64_decode($encrypted),
                $decrypted,
                file_get_contents($path)
            );

            if ($decrypted !== null) {
                return $decrypted;
            }
        }

        if (!$keysExist) {
            Log::critical('RSA keys missing', ['slots_checked' => [$slot, $slot - 1]]);
            $this->audit('rsa_missing', '', ['slots' => [$slot, $slot - 1]]);
        }

        throw new AuthenticationException('Credenciales incorrectas.');
    }

    private function enforceSesionLimit(SiawUsuarios $usuario): void
    {
        $count = $usuario->tokens()->count();

        if ($count >= self::MAX_SESSIONS) {
            $usuario->tokens()
                ->orderBy('created_at')
                ->limit($count - self::MAX_SESSIONS + 1)
                ->delete();
        }
    }

    // ── Bloqueo por email ─────────────────────────────────────────────────

    private function checkEmailBlock(string $email): void
    {
        if (cache()->has("login_blocked_{$email}")) {
            $intentos = cache()->get("login_attempts_{$email}", 0);
            $this->audit('account_blocked_check', $email);
            throw new AuthenticationException(
                "Cuenta bloqueada por {$intentos} intentos fallidos. Espere " . self::BLOCK_MINUTES . ' minutos o contacte al administrador.'
            );
        }
    }

    private function trackFailedAttempt(string $email): void
    {
        $key      = "login_attempts_{$email}";
        $attempts = (int) cache()->get($key, 0) + 1;

        cache()->put($key, $attempts, now()->addMinutes(self::BLOCK_MINUTES));

        if ($attempts >= self::MAX_ATTEMPTS) {
            cache()->put("login_blocked_{$email}", true, now()->addMinutes(self::BLOCK_MINUTES));
            $this->audit('account_blocked', $email, ['intentos' => $attempts]);
        }

        $this->trackIpAttempt();
    }

    private function clearFailedAttempts(string $email): void
    {
        cache()->forget("login_attempts_{$email}");
        cache()->forget("login_blocked_{$email}");
    }

    // ── Bloqueo por IP ────────────────────────────────────────────────────

    private function checkIpBlock(): void
    {
        $ip  = request()->ip();
        $key = "login_ip_attempts_{$ip}";

        if ((int) cache()->get($key, 0) >= self::MAX_IP_ATTEMPTS) {
            $this->audit('ip_blocked', '', ['ip' => $ip]);
            throw new AuthenticationException('Demasiados intentos desde esta dirección. Intente en ' . self::IP_BLOCK_MINUTES . ' minutos.');
        }
    }

    private function trackIpAttempt(): void
    {
        $key = 'login_ip_attempts_' . request()->ip();
        $n   = (int) cache()->get($key, 0) + 1;
        cache()->put($key, $n, now()->addMinutes(self::IP_BLOCK_MINUTES));
    }

    private function clearIpAttempts(): void
    {
        cache()->forget('login_ip_attempts_' . request()->ip());
    }

    // ── Audit log de seguridad ────────────────────────────────────────────

    private function audit(string $evento, string $email, array $contexto = []): void
    {
        try {
            DB::connection('dbsiaw')->table('siaw_security_logs')->insert([
                'id'         => Str::uuid()->toString(),
                'evento'     => $evento,
                'email'      => $email ?: null,
                'ip'         => request()->ip(),
                'user_agent' => request()->userAgent(),
                'contexto'   => $contexto ? json_encode($contexto) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Security audit failed', ['evento' => $evento, 'error' => $e->getMessage()]);
        }
    }
}
