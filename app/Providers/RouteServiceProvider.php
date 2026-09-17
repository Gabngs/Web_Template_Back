<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Carga automáticamente todos los *.php de routes/modules/, ya
        // envueltos en el stack de seguridad completo (ver Seguridad.md del
        // estándar: session.key + auth:sanctum + throttle:api + forzar.cambio).
        // Cada archivo de módulo NO repite este middleware — solo agrega,
        // si le hace falta, el sub-grupo `permiso:...` para sus rutas
        // sensibles (create/update/destroy). Para agregar un módulo nuevo:
        // crear routes/modules/{nombre}.php.
        //
        // Las rutas públicas de auth (login, challenge, public-key) NO viven
        // acá — están en routes/api/api.php, cargadas por bootstrap/app.php
        // vía withRouting(api: ...), fuera de este stack protegido.
        $files = glob(base_path('routes/modules') . DIRECTORY_SEPARATOR . '*.php') ?: [];

        Route::prefix('api')
            ->middleware(['session.key', 'auth:sanctum', 'throttle:api', 'forzar.cambio'])
            ->group(function () use ($files) {
                foreach ($files as $file) {
                    require $file;
                }
            });
    }
}
