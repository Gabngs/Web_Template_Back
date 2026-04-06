<?php
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// ─── Rutas públicas ───────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// ─── Rutas protegidas — KeycloakAuth ─────────────────────────────────────────
// Valida JWT firmado por Keycloak sin tabla de tokens en DB
Route::middleware('keycloak')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',     [AuthController::class, 'me']);
    });

    // ─── Módulos de negocio ───────────────────────────────────────────────────
    // Generados con: python scripts/generator.py scripts/examples/mi_modulo.json
    foreach (glob(__DIR__ . '/modules/*.php') as $moduleRoute) {
        require $moduleRoute;
    }
});
