<?php

use App\Http\Controllers\Api\Siaw\SiawPermisoUsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('siaw_permiso_usuario',                        [SiawPermisoUsuarioController::class, 'index']);
Route::get('siaw_permiso_usuario/{siaw_permiso_usuario}', [SiawPermisoUsuarioController::class, 'show']);

Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::post('siaw_permiso_usuario/sync',                     [SiawPermisoUsuarioController::class, 'sync']);
    Route::post('siaw_permiso_usuario',                          [SiawPermisoUsuarioController::class, 'store']);
    Route::put('siaw_permiso_usuario/{siaw_permiso_usuario}',    [SiawPermisoUsuarioController::class, 'update']);
    Route::delete('siaw_permiso_usuario/{siaw_permiso_usuario}', [SiawPermisoUsuarioController::class, 'destroy']);
});
