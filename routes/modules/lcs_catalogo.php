<?php

use App\Http\Controllers\Api\Lcs\LcsCatalogoController;
use Illuminate\Support\Facades\Route;

Route::apiResource('lcs_catalogo', LcsCatalogoController::class)
    ->parameters(['lcs_catalogo' => 'lcs_catalogo']);
