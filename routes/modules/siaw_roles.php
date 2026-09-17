<?php

use App\Http\Controllers\Api\Siaw\SiawRolesController;
use Illuminate\Support\Facades\Route;

Route::get('siaw_roles',              [SiawRolesController::class, 'index']);
Route::get('siaw_roles/{siaw_role}',  [SiawRolesController::class, 'show']);

Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::post('siaw_roles',               [SiawRolesController::class, 'store']);
    Route::put('siaw_roles/{siaw_role}',    [SiawRolesController::class, 'update']);
    Route::delete('siaw_roles/{siaw_role}', [SiawRolesController::class, 'destroy']);
});
