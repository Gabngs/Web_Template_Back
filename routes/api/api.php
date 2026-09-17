<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => 'LCS Backend API funcionando');

// ── Autenticación pública ──────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::get('/public-key', [AuthController::class, 'getPublicKey']);
    Route::get('/challenge',  [AuthController::class, 'challenge'])->middleware('throttle:30,1');
    Route::post('/login',     [AuthController::class, 'login'])->middleware('throttle:login');

    // Solo desarrollo — password en texto plano, hace el RSA+challenge acá
    // mismo. Ver app/Http/Middleware/EnsureDevEndpointsEnabled.php (404 si
    // APP_ENV=production).
    Route::post('/login-dev', [AuthController::class, 'loginDev'])
        ->middleware(['dev.only', 'throttle:login']);
});

// ── Protegidas ─────────────────────────────────────────────────────────────
Route::middleware(['session.key', 'auth:sanctum', 'throttle:api', 'forzar.cambio'])->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
        Route::get('/permisos', [AuthController::class, 'permisos']);
        Route::post('/cambiar-password', [AuthController::class, 'cambiarPassword']);

        Route::get('/sessions',         [AuthController::class, 'sessions']);
        Route::delete('/sessions',      [AuthController::class, 'revokeAllSessions']);
        Route::delete('/sessions/{id}', [AuthController::class, 'revokeSession']);

        // Solo superuser o admin pueden ejecutar estas acciones
        Route::post('/reset-password/{id}', [AuthController::class, 'resetPassword'])
            ->middleware('permiso:isSuperUser,isAdmin');

        Route::post('/unblock/{email}', [AuthController::class, 'unblockUser'])
            ->middleware('permiso:isSuperUser,isAdmin');
    });
});
