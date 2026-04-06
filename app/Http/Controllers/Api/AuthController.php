<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
 * @OA\Tag(name="Auth", description="Autenticación vía Keycloak (Identity Provider)")
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
}
