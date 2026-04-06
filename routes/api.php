<?php
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// ─── Rutas públicas ───────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// ─── Rutas protegidas con Sanctum ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',     [AuthController::class, 'me']);
    });

    // ─── Módulos de negocio ───────────────────────────────────────────────────
    // Cada módulo tiene su propio archivo en routes/modules/
    // El script generador (scripts/generator.py) crea estos archivos automáticamente.
    //
    // Ejemplo:
    //   require __DIR__ . '/modules/productos.php';
    //   require __DIR__ . '/modules/categorias.php';

    // Auto-carga de todos los archivos en routes/modules/ (si existen)
    foreach (glob(__DIR__ . '/modules/*.php') as $moduleRoute) {
        require $moduleRoute;
    }
});
