<?php

use App\Http\Controllers\Api\Siaw\SiawParametrosController;
use Illuminate\Support\Facades\Route;

// Lectura abierta a cualquier autenticado; la escritura de parámetros de
// configuración solo para superusuario/admin (igual que siaw_sistemas, siaw_menus...).
Route::apiResource('siaw_parametros', SiawParametrosController::class)
    ->only(['index', 'show'])
    ->parameters(['siaw_parametros' => 'siaw_parametros']);

Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::apiResource('siaw_parametros', SiawParametrosController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['siaw_parametros' => 'siaw_parametros']);
});
