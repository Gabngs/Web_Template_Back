<?php

use App\Http\Controllers\Api\Siaw\SiawRolUsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('siaw_rol_usuario',                      [SiawRolUsuarioController::class, 'index']);
Route::get('siaw_rol_usuario/{siaw_rol_usuario}',   [SiawRolUsuarioController::class, 'show']);

Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::post('siaw_rol_usuario/sync',                  [SiawRolUsuarioController::class, 'sync']);
    Route::post('siaw_rol_usuario',                       [SiawRolUsuarioController::class, 'store']);
    Route::put('siaw_rol_usuario/{siaw_rol_usuario}',     [SiawRolUsuarioController::class, 'update']);
    Route::delete('siaw_rol_usuario/{siaw_rol_usuario}',  [SiawRolUsuarioController::class, 'destroy']);
});
