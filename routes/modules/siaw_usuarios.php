<?php

use App\Http\Controllers\Api\Siaw\SiawUsuariosController;
use Illuminate\Support\Facades\Route;

// Gestión de usuarios — solo superuser/admin pueden crear, editar o eliminar.
// La lectura (index/show) queda abierta a cualquier autenticado; ajustar si
// el negocio lo requiere más restrictivo.
Route::get('siaw_usuarios',                [SiawUsuariosController::class, 'index']);
Route::get('siaw_usuarios/{siaw_usuario}', [SiawUsuariosController::class, 'show']);

Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::post('siaw_usuarios',                [SiawUsuariosController::class, 'store']);
    Route::put('siaw_usuarios/{siaw_usuario}',   [SiawUsuariosController::class, 'update']);
    Route::delete('siaw_usuarios/{siaw_usuario}', [SiawUsuariosController::class, 'destroy']);
});
