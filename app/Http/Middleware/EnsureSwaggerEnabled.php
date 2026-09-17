<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSwaggerEnabled
{
    /**
     * Bloquea toda la UI/JSON de Swagger (/api/documentation, /docs, assets)
     * cuando L5_SWAGGER_ENABLED=false — por defecto false en producción.
     * 404 en vez de 403 para no confirmar que la ruta existe.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('l5-swagger.documentations.default.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
