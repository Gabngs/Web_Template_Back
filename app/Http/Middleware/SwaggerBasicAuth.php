<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SwaggerBasicAuth – Protege la documentación Swagger con HTTP Basic Auth
 * cuando el entorno NO es local (staging, producción).
 *
 * Credenciales configurables desde .env:
 *   SWAGGER_USER=docs
 *   SWAGGER_PASSWORD=secreto
 */
class SwaggerBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // En local no requiere autenticación
        if (app()->environment('local')) {
            return $next($request);
        }

        $user     = config('app.swagger_user',     env('SWAGGER_USER'));
        $password = config('app.swagger_password', env('SWAGGER_PASSWORD'));

        // Si no hay credenciales configuradas, bloquear acceso en producción
        if (empty($user) || empty($password)) {
            abort(403, 'Swagger deshabilitado: configure SWAGGER_USER y SWAGGER_PASSWORD en .env');
        }

        if ($request->getUser() !== $user || $request->getPassword() !== $password) {
            return response('Acceso restringido a documentación API', 401, [
                'WWW-Authenticate' => 'Basic realm="API Docs"',
            ]);
        }

        return $next($request);
    }
}
