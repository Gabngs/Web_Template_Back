<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * @OA\Info(
 *     title="Web Template API",
 *     version="1.0.0",
 *     description="API base del Web Template con autenticación Keycloak (OAuth2/OIDC)."
 * )
 * @OA\Server(url=L5_SWAGGER_CONST_HOST, description="Servidor actual")
 * @OA\SecurityScheme(
 *     securityScheme="keycloak",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Token JWT obtenido desde Keycloak. Ver POST /api/auth/login (solo dev)."
 * )
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Token Bearer Sanctum — obtenido desde POST /api/auth/google (usuarios Google)."
 * )
 * @OA\Tag(name="Auth", description="Autenticación vía Keycloak (Identity Provider) o Google GSI")
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Login — obtener JWT desde Keycloak (solo desarrollo/testing)",
     *     description="Modo dev: intercambia credenciales por JWT directamente (direct grant).
     *     En producción el frontend usa flujo Authorization Code con PKCE.",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password"},
     *             @OA\Property(property="username", type="string", example="admin"),
     *             @OA\Property(property="password", type="string", example="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="JWT obtenido exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=21600),
     *             @OA\Property(property="refresh_token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Credenciales incorrectas")
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $response = Http::asForm()->post(config('keycloak.token_uri'), [
            'grant_type'    => 'password',
            'client_id'     => config('keycloak.client_id'),
            'client_secret' => config('keycloak.client_secret'),
            'username'      => $request->username,
            'password'      => $request->password,
            'scope'         => 'openid profile email',
        ]);

        if (!$response->ok()) {
            return response()->json([
                'message' => 'Credenciales incorrectas o usuario no existe en Keycloak',
            ], 401);
        }

        $data = $response->json();

        return response()->json([
            'access_token'  => $data['access_token'],
            'token_type'    => 'Bearer',
            'expires_in'    => $data['expires_in'],
            'refresh_token' => $data['refresh_token'] ?? null,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Logout — revocar sesión en Keycloak",
     *     tags={"Auth"},
     *     security={{"keycloak":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="refresh_token", type="string",
     *                 description="Refresh token para invalidar la sesión completa en Keycloak")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sesión cerrada")
     * )
     */
    public function logout(Request $request)
    {
        $refreshToken = $request->input('refresh_token');

        if ($refreshToken) {
            Http::asForm()->post(config('keycloak.logout_uri'), [
                'client_id'     => config('keycloak.client_id'),
                'client_secret' => config('keycloak.client_secret'),
                'refresh_token' => $refreshToken,
            ]);
        }

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     summary="Datos del usuario autenticado + roles",
     *     tags={"Auth"},
     *     security={{"keycloak":{}}},
     *     @OA\Response(response=200, description="Datos del usuario")
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('roles');

        return response()->json([
            'user'  => $user->only(['id', 'name', 'email', 'avatar', 'is_active']),
            'roles' => $user->roles->pluck('name'),
        ]);
    }

    // ─── Login con Google ─────────────────────────────────────────────────────

    /**
     * @OA\Post(
     *     path="/api/auth/google",
     *     summary="Login / Registro automático con Google Identity Services",
     *     description="Recibe el `credential` (ID Token JWT) emitido por la librería GSI de Google.
     *     El backend verifica la firma contra las claves públicas JWKS de Google,
     *     valida `aud` y `iss`, y realiza self-provisioning si el usuario no existe.
     *     Devuelve un Bearer token Sanctum propio de la plataforma.
     *     Si `requires_password_setup` es true, el frontend debe mostrar el modal de contraseña.",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id_token"},
     *             @OA\Property(property="id_token", type="string", description="credential obtenido de Google GSI")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="expires_in", type="integer"),
     *             @OA\Property(property="requires_password_setup", type="boolean"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="user_avatar_url", type="string", nullable=true),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="ID Token inválido o expirado"),
     *     @OA\Response(response=403, description="Usuario inactivo"),
     *     @OA\Response(response=422, description="GOOGLE_CLIENT_ID no configurado en .env")
     * )
     */
    public function loginWithGoogle(Request $request, GoogleAuthService $googleService)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $payload = $googleService->verifyIdToken($request->id_token);

        if (!$payload) {
            return response()->json(['message' => 'ID Token de Google inválido o expirado.'], 401);
        }

        $user = $googleService->findOrCreateUser($payload);

        if (!$user->is_active) {
            return response()->json(['message' => 'Usuario inactivo.'], 403);
        }

        $user->tokens()->delete();
        $minutes   = (int) config('sanctum.expiration', 360);
        $expiresAt = now()->addMinutes($minutes);
        $token     = $user->createToken('api-token', ['*'], $expiresAt);

        return response()->json([
            'access_token'            => $token->plainTextToken,
            'token_type'              => 'Bearer',
            'expires_in'              => $minutes * 60,
            'requires_password_setup' => is_null($user->password_set_at),
            'user'                    => [
                'id'              => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'user_avatar_url' => $user->user_avatar_url,
                'roles'           => $user->roles->pluck('name'),
            ],
        ]);
    }

    // ─── Establecer contraseña local (usuarios de Google) ────────────────────

    /**
     * @OA\Post(
     *     path="/api/auth/setup-password",
     *     summary="Establecer contraseña local para usuarios registrados con Google",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password","password_confirmation"},
     *             @OA\Property(property="password", type="string", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Contraseña establecida correctamente"),
     *     @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function setupPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $request->user()->update([
            'password'        => $request->password,
            'password_set_at' => now(),
        ]);

        return response()->json(['message' => 'Contraseña establecida correctamente.']);
    }
}
