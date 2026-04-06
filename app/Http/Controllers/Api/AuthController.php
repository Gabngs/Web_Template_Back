<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
 *     description="Token Bearer obtenido desde POST /api/auth/login"
 * )
 * @OA\Tag(name="Auth", description="Autenticación con tokens Bearer Sanctum")
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Login - obtener Bearer token",
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
     *             @OA\Property(property="expires_in", type="integer", example=86400),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
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
        $minutes    = (int) config('sanctum.expiration', 360);
        $expiresAt  = now()->addMinutes($minutes);
        $token      = $user->createToken('api-token', ['*'], $expiresAt);

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => $minutes * 60,
            'user'         => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
            ],
        ]);
    }

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
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

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
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('roles.permissions');

        $permissions = $user->roles->flatMap(fn($role) => $role->permissions->pluck('name'))->unique()->values();

        return response()->json([
            'user'        => $user->only(['id', 'name', 'email', 'avatar', 'is_active']),
            'roles'       => $user->roles->pluck('name'),
            'permissions' => $permissions,
        ]);
    }
}
