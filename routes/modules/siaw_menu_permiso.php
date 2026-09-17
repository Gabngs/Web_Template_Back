<?php

use App\Http\Controllers\Api\Siaw\SiawMenuPermisoController;
use Illuminate\Support\Facades\Route;

Route::get('siaw_menu_permiso',                       [SiawMenuPermisoController::class, 'index']);
Route::get('siaw_menu_permiso/{siaw_menu_permiso}',   [SiawMenuPermisoController::class, 'show']);

Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::post('siaw_menu_permiso',                      [SiawMenuPermisoController::class, 'store']);
    Route::put('siaw_menu_permiso/{siaw_menu_permiso}',   [SiawMenuPermisoController::class, 'update']);
    Route::delete('siaw_menu_permiso/{siaw_menu_permiso}', [SiawMenuPermisoController::class, 'destroy']);
});
