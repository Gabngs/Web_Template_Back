<?php
namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Web Template API",
 *     version="1.0.0",
 *     description="API base - Web_Template_Back"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token",
 *     description="Token obtenido en POST /api/auth/login. Pegar solo el token, sin la palabra Bearer."
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Servidor local"
 * )
 */
abstract class Controller
{
    //
}