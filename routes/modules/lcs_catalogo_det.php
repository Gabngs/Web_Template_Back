<?php

use App\Http\Controllers\Api\Lcs\LcsCatalogoDetController;
use Illuminate\Support\Facades\Route;

Route::apiResource('lcs_catalogo_det', LcsCatalogoDetController::class)
    ->parameters(['lcs_catalogo_det' => 'lcs_catalogo_det']);
