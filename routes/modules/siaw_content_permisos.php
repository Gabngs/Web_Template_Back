<?php

use App\Http\Controllers\Api\Siaw\SiawContentPermisosController;
use Illuminate\Support\Facades\Route;

Route::get('siaw_content_permisos',                        [SiawContentPermisosController::class, 'index']);
Route::get('siaw_content_permisos/{siaw_content_permiso}', [SiawContentPermisosController::class, 'show']);

Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::post('siaw_content_permisos/bulk',                    [SiawContentPermisosController::class, 'bulkCreate']);
    Route::post('siaw_content_permisos',                        [SiawContentPermisosController::class, 'store']);
    Route::put('siaw_content_permisos/{siaw_content_permiso}',  [SiawContentPermisosController::class, 'update']);
    Route::delete('siaw_content_permisos/{siaw_content_permiso}', [SiawContentPermisosController::class, 'destroy']);
});
