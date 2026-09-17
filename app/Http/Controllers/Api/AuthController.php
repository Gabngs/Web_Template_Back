<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CambiarPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\dbsiaw\SiawUsuarios;
use App\Services\Auth\AuthService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $authService) {}

    // ── Públicos ──────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *      path="/api/auth/public-key",
     *      operationId="getPublicKey",
     *      tags={"Autenticacion"},
     *      summary="Clave pública RSA del slot actual (rota cada 2h)",
     *      @OA\Response(response=200, description="Clave pública RSA")
     * )
     */
    public function getPublicKey(): JsonResponse
    {
        $path = AuthService::publicKeyPath();

        if (!file_exists($path)) {
            return $this->responseServerError(
                'Claves no generadas. Ejecute: php artisan auth:rotate-keys',
                'Error de configuración del servidor',
            );
        }

        return $this->responseSuccess(
            'Clave pública RSA',
            ['public_key' => file_get_contents($path)],
        );
    }

    /**
     * @OA\Get(
     *      path="/api/auth/challenge",
     *      operationId="getChallenge",
     *      tags={"Autenticacion"},
     *      summary="Obtener nonce de un solo uso para el login (expira en 30s)",
     *      @OA\Response(
     *          response=200,
     *          description="Nonce generado",
     *          @OA\JsonContent(
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="nonce", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                  @OA\Property(property="expira_en", type="string")
     *              )
     *          )
     *      )
     * )
     */
    public function challenge(): JsonResponse
    {
        $result = $this->authService->challenge();

        return $this->responseSuccess($result['message'], $result['data']);
    }

    /**
     * @OA\Post(
     *      path="/api/auth/login",
     *      operationId="loginUser",
     *      tags={"Autenticacion"},
     *      summary="Login — requiere nonce de /auth/challenge y password encriptado RSA",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email","password","nonce"},
     *              @OA\Property(property="email",    type="string",
     *                  description="Email o código de usuario (5 dígitos, autogenerado — ej: 00001)"),
     *              @OA\Property(property="password", type="string", description="RSA+base64(plainPassword)"),
     *              @OA\Property(property="nonce",    type="string", format="uuid",
     *                  description="UUID obtenido de GET /auth/challenge (válido 30s, un solo uso)"),
     *              @OA\Property(property="device",   type="string", enum={"web","mobile","tablet"},
     *                  description="Identificador del dispositivo (default: web)")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Login exitoso",
     *          @OA\JsonContent(
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="token", type="string",
     *                      description="Bearer completo: {session_key}@{sanctum_token}"),
     *                  @OA\Property(property="debe_cambiar_password", type="boolean")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=401, description="Credenciales incorrectas / challenge inválido / IP bloqueada"),
     *      @OA\Response(response=422, description="Validación fallida"),
     *      @OA\Response(response=429, description="Demasiados intentos")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            return $this->responseSuccess($result['message'], $result['data']);
        } catch (AuthenticationException $e) {
            return $this->responseUnAuthenticated($e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *      path="/api/auth/login-dev",
     *      operationId="loginDev",
     *      tags={"Autenticacion"},
     *      summary="[SOLO DEV] Login con password en texto plano — 404 en producción",
     *      description="Hace acá mismo lo que en producción hace el frontend (challenge + encriptado RSA) para poder probar la API desde Swagger/curl sin armar el paso de encriptación a mano. Gateado por el middleware dev.only (app()->environment('production') → 404).",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email","password"},
     *              @OA\Property(property="email",    type="string", description="Email o código de usuario"),
     *              @OA\Property(property="password", type="string", description="Password en TEXTO PLANO — solo dev"),
     *              @OA\Property(property="device",   type="string", description="Default: dev")
     *          )
     *      ),
     *      @OA\Response(response=200, description="Login exitoso — mismo shape que /auth/login"),
     *      @OA\Response(response=401, description="Credenciales incorrectas"),
     *      @OA\Response(response=404, description="No disponible en producción")
     * )
     */
    public function loginDev(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'string'],
            'password' => ['required', 'string'],
            'device'   => ['nullable', 'string'],
        ]);

        try {
            $result = $this->authService->loginDev($data);

            return $this->responseSuccess($result['message'], $result['data']);
        } catch (AuthenticationException $e) {
            return $this->responseUnAuthenticated($e->getMessage());
        }
    }

    // ── Protegidos ────────────────────────────────────────────────────────

    /**
     * @OA\Post(
     *      path="/api/auth/logout",
     *      operationId="logoutUser",
     *      tags={"Autenticacion"},
     *      summary="Cerrar sesión actual",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(response=200, description="Sesión cerrada")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $result = $this->authService->logout($request->user());

        return $this->responseSuccess($result['message']);
    }

    /**
     * @OA\Get(
     *      path="/api/auth/me",
     *      operationId="getMeUser",
     *      tags={"Autenticacion"},
     *      summary="Datos del usuario autenticado",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(response=200, description="Datos del usuario")
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $sesion  = $this->authService->armarPaqueteSesion($usuario);

        return $this->responseSuccess('Usuario autenticado', [
            'id'                    => $usuario->id,
            'nombre'                => $usuario->nombre,
            'apellidos'             => $usuario->apellidos,
            'email'                 => $usuario->email,
            'codigo'                => $usuario->codigo,
            'activo'                => (bool) $usuario->activo,
            'ultimo_acceso_en'      => $usuario->ultimo_acceso_en?->toDateTimeString(),
            'debe_cambiar_password' => $usuario->debe_cambiar_password,
            ...$sesion,
        ]);
    }

    /**
     * @OA\Post(
     *      path="/api/auth/cambiar-password",
     *      operationId="cambiarPassword",
     *      tags={"Autenticacion"},
     *      summary="El usuario autenticado cambia su propia contraseña (self-service)",
     *      description="Exenta del bloqueo de forzar.cambio — es la vía de salida cuando debe_cambiar_password=true (alta con password inicial o reset por un admin). También sirve para cambio voluntario.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"password_actual","password_nueva"},
     *              @OA\Property(property="password_actual", type="string", description="RSA+base64(passwordActual)"),
     *              @OA\Property(property="password_nueva",   type="string", description="RSA+base64(passwordNueva), mínimo 8 caracteres tras desencriptar")
     *          )
     *      ),
     *      @OA\Response(response=200, description="Contraseña actualizada"),
     *      @OA\Response(response=401, description="Contraseña actual incorrecta"),
     *      @OA\Response(response=422, description="Nueva contraseña inválida (muy corta o igual a la actual)")
     * )
     */
    public function cambiarPassword(CambiarPasswordRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->cambiarPassword(
                $request->user(),
                $request->validated('password_actual'),
                $request->validated('password_nueva'),
            );

            return $this->responseSuccess($result['message']);
        } catch (AuthenticationException $e) {
            return $this->responseUnAuthenticated($e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *      path="/api/auth/sessions",
     *      operationId="getSessions",
     *      tags={"Autenticacion"},
     *      summary="Listar sesiones activas del usuario",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(response=200, description="Lista de sesiones")
     * )
     */
    public function sessions(Request $request): JsonResponse
    {
        $result = $this->authService->sessions($request->user());

        return $this->responseSuccess($result['message'], $result['data']);
    }

    /**
     * @OA\Get(
     *      path="/api/auth/permisos",
     *      operationId="getPermisos",
     *      tags={"Autenticacion"},
     *      summary="Paquete de sesión: rol, codenames de permisos y menús visibles del usuario autenticado",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(response=200, description="Rol, permisos y menús del usuario")
     * )
     */
    public function permisos(Request $request): JsonResponse
    {
        $result = $this->authService->permisos($request->user());

        return $this->responseSuccess($result['message'], $result['data']);
    }

    /**
     * @OA\Delete(
     *      path="/api/auth/sessions/{id}",
     *      operationId="revokeSession",
     *      tags={"Autenticacion"},
     *      summary="Revocar una sesión específica por ID",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *      @OA\Response(response=200, description="Sesión revocada")
     * )
     */
    public function revokeSession(Request $request, int $id): JsonResponse
    {
        $result = $this->authService->revokeSession($request->user(), $id);

        return $this->responseSuccess($result['message']);
    }

    /**
     * @OA\Delete(
     *      path="/api/auth/sessions",
     *      operationId="revokeAllSessions",
     *      tags={"Autenticacion"},
     *      summary="Cerrar todas las sesiones del usuario",
     *      security={{"bearerAuth":{}}},
     *      @OA\Response(response=200, description="Todas las sesiones cerradas")
     * )
     */
    public function revokeAllSessions(Request $request): JsonResponse
    {
        $result = $this->authService->revokeAllSessions($request->user());

        return $this->responseSuccess($result['message']);
    }

    /**
     * @OA\Post(
     *      path="/api/auth/reset-password/{id}",
     *      operationId="resetPassword",
     *      tags={"Autenticacion"},
     *      summary="Restablecer contraseña a un valor temporal aleatorio — solo admin",
     *      description="La contraseña temporal se envía por correo al usuario — nunca viaja en la respuesta HTTP.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, description="UUID del usuario",
     *          @OA\Schema(type="string")),
     *      @OA\Response(response=200, description="Contraseña restablecida y enviada por correo"),
     *      @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function resetPassword(string $id): JsonResponse
    {
        $usuario = SiawUsuarios::where('id', $id)->firstOrFail();
        $result  = $this->authService->resetPassword($usuario);

        return $this->responseSuccess($result['message']);
    }

    /**
     * @OA\Post(
     *      path="/api/auth/unblock/{email}",
     *      operationId="unblockUser",
     *      tags={"Autenticacion"},
     *      summary="Desbloquear cuenta bloqueada por intentos fallidos — solo admin",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="email", in="path", required=true, @OA\Schema(type="string", format="email")),
     *      @OA\Response(response=200, description="Usuario desbloqueado")
     * )
     */
    public function unblockUser(string $email): JsonResponse
    {
        $result = $this->authService->unblockUser($email);

        return $this->responseSuccess($result['message']);
    }
}
