<?php

use App\Http\Controllers\Api\Siaw\SiawMenusController;
use Illuminate\Support\Facades\Route;

// Lectura abierta a cualquier autenticado — el frontend arma su sidebar
// filtrando client-side por los permisos que ya trae en /auth/me (o los
// resuelve consultando siaw_menu_permiso).
Route::get('siaw_menus',                 [SiawMenusController::class, 'index']);
Route::get('siaw_menus/{siaw_menu}',     [SiawMenusController::class, 'show']);

Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::post('siaw_menus',                [SiawMenusController::class, 'store']);
    Route::put('siaw_menus/{siaw_menu}',     [SiawMenusController::class, 'update']);
    Route::delete('siaw_menus/{siaw_menu}',  [SiawMenusController::class, 'destroy']);
});
