<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 * title="LCS Backend API",
 * version="1.0.0",
 * description="Documentación de la API para LCS Backend"
 * )
 *
 * @OA\Server(
 * url=L5_SWAGGER_CONST_HOST,
 * description="Servidor Principal"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     description="Token de sesión retornado por /api/auth/login (campo data.token). No copiar solo la parte de Sanctum ni recomponerlo manualmente."
 * )
 */
abstract class Controller
{
    //
}
