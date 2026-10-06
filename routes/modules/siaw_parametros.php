<?php

use App\Http\Controllers\Api\Siaw\SiawParametrosController;
use Illuminate\Support\Facades\Route;

Route::apiResource('siaw_parametros', SiawParametrosController::class)
    ->parameters(['siaw_parametros' => 'siaw_parametros']);
