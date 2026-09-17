<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForzarCambioPassword
{
    private const RUTAS_EXENTAS = [
        'api/auth/logout',
        'api/auth/cambiar-password',
        'api/auth/me',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario?->debe_cambiar_password) {
            foreach (self::RUTAS_EXENTAS as $ruta) {
                if ($request->is($ruta)) {
                    return $next($request);
                }
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Debe cambiar su contraseña antes de continuar.',
                'accion'  => 'cambiar_password',
            ], 403);
        }

        return $next($request);
    }
}
