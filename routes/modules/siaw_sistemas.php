<?php

use App\Http\Controllers\Api\Siaw\SiawSistemasController;
use Illuminate\Support\Facades\Route;

Route::get('siaw_sistemas',                    [SiawSistemasController::class, 'index']);
Route::get('siaw_sistemas/{siaw_sistema}',     [SiawSistemasController::class, 'show']);

Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::post('siaw_sistemas',                   [SiawSistemasController::class, 'store']);
    Route::put('siaw_sistemas/{siaw_sistema}',     [SiawSistemasController::class, 'update']);
    Route::delete('siaw_sistemas/{siaw_sistema}',  [SiawSistemasController::class, 'destroy']);
});
