<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDevEndpointsEnabled
{
    /**
     * Bloquea rutas de conveniencia que solo tienen sentido en desarrollo
     * (ej. /auth/login-dev, que hace el login completo con password en
     * texto plano). 404 en vez de 403 para no confirmar que la ruta existe.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production')) {
            abort(404);
        }

        return $next($request);
    }
}
