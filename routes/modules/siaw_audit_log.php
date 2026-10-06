<?php

use App\Http\Controllers\Api\Siaw\SiawAuditLogController;
use Illuminate\Support\Facades\Route;

// Un log de auditoría no debe ser editable por cualquier usuario autenticado:
// lectura y escritura solo para superusuario/admin.
Route::middleware('permiso:isSuperUser,isAdmin')->group(function () {
    Route::apiResource('siaw_audit_log', SiawAuditLogController::class)
        ->parameters(['siaw_audit_log' => 'siaw_audit_log']);
});
