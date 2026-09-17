<?php

use App\Http\Controllers\Api\Siaw\SiawContentModelController;
use Illuminate\Support\Facades\Route;

Route::get('siaw_content_model',                       [SiawContentModelController::class, 'index']);
Route::get('siaw_content_model/{siaw_content_model}',  [SiawContentModelController::class, 'show']);

Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::post('siaw_content_model',                      [SiawContentModelController::class, 'store']);
    Route::put('siaw_content_model/{siaw_content_model}',  [SiawContentModelController::class, 'update']);
    Route::delete('siaw_content_model/{siaw_content_model}', [SiawContentModelController::class, 'destroy']);
});
