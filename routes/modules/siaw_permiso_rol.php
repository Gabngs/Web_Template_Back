<?php

use App\Http\Controllers\Api\Siaw\SiawPermisoRolController;
use Illuminate\Support\Facades\Route;

Route::get('siaw_permiso_rol',                        [SiawPermisoRolController::class, 'index']);
Route::get('siaw_permiso_rol/{siaw_permiso_rol}',     [SiawPermisoRolController::class, 'show']);

Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::post('siaw_permiso_rol/sync',                  [SiawPermisoRolController::class, 'sync']);
    Route::post('siaw_permiso_rol',                       [SiawPermisoRolController::class, 'store']);
    Route::put('siaw_permiso_rol/{siaw_permiso_rol}',     [SiawPermisoRolController::class, 'update']);
    Route::delete('siaw_permiso_rol/{siaw_permiso_rol}',  [SiawPermisoRolController::class, 'destroy']);
});
