<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Info(
 *     title="Web Template API",
 *     version="1.0.0",
 *     description="API base del Web Template. Clona este proyecto para nuevos proyectos."
 * )
 * @OA\Server(url=L5_SWAGGER_CONST_HOST, description="Servidor actual")
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Token Bearer obtenido desde POST /api/auth/login o POST /api/auth/google"
 * )
 * @OA\Tag(name="Auth", description="Autenticación con tokens Bearer Sanctum")
 */
class AuthController extends Controller
{
    // ─── Login email + password ───────────────────────────────────────────────

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Login con email y contraseña",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", example="admin@template.com"),
     *             @OA\Property(property="password", type="string", example="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=21600),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="user_avatar_url", type="string", nullable=true),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Credenciales incorrectas"),
     *     @OA\Response(response=403, description="Usuario inactivo")
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Usuario inactivo'], 403);
        }

        $user->tokens()->delete();
        $minutes   = (int) config('sanctum.expiration', 360);
        $expiresAt = now()->addMinutes($minutes);
        $token     = $user->createToken('api-token', ['*'], $expiresAt);

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => $minutes * 60,
            'user'         => [
                'id'              => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'user_avatar_url' => $user->user_avatar_url,
                'roles'           => $user->roles->pluck('name'),
            ],
        ]);
    }

    // ─── Login con Google ─────────────────────────────────────────────────────

    /**
     * @OA\Post(
     *     path="/api/auth/google",
     *     summary="Login / Registro automático con Google Identity Services",
     *     description="Recibe el `credential` (ID Token JWT) emitido por la librería GSI de Google.
     *     El backend verifica la firma contra las claves públicas de Google (JWKS),
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
     *             @OA\Property(property="requires_password_setup", type="boolean",
     *                 description="true si el usuario nunca estableció contraseña local"),
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
     *     @OA\Response(response=403, description="Usuario inactivo")
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
     *     description="Permite a los usuarios que se registraron vía Google establecer
     *     una contraseña local para poder iniciar sesión también con email + contraseña.",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password","password_confirmation"},
     *             @OA\Property(property="password", type="string", minLength=8, example="MiClave123!"),
     *             @OA\Property(property="password_confirmation", type="string", example="MiClave123!")
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

    // ─── Logout ───────────────────────────────────────────────────────────────

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Logout - revocar token actual",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Sesión cerrada")
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    // ─── Me ───────────────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     summary="Datos del usuario autenticado + roles + permisos",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Datos del usuario",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="user_avatar_url", type="string", nullable=true),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="requires_password_setup", type="boolean")
     *             ),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user        = $request->user()->load('roles.permissions');
        $permissions = $user->roles
            ->flatMap(fn($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();

        return response()->json([
            'user' => [
                'id'                      => $user->id,
                'name'                    => $user->name,
                'email'                   => $user->email,
                'user_avatar_url'         => $user->user_avatar_url,
                'is_active'               => $user->is_active,
                'requires_password_setup' => is_null($user->password_set_at),
            ],
            'roles'       => $user->roles->pluck('name'),
            'permissions' => $permissions,
        ]);
    }
}
