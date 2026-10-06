<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;

class SiawAuditLogFilters extends QueryFilters
{
    // input/output son JSON: no se filtran ni ordenan por ellos.
    protected array $columnSearch = [
        'nombre_proceso',
        'modelo_afectado',
        'registro_id',
        'error',
    ];

    protected array $allowedFilters = [
        'nombre_proceso',
        'tipo_proceso',
        'estado_proceso',
        'origen',
        'modelo_afectado',
        'registro_id',
    ];

    protected array $allowedIncludes = [
        'created_by',
    ];

    protected array $allowedSorts = [
        'nombre_proceso',
        'tipo_proceso',
        'estado_proceso',
        'origen',
        'modelo_afectado',
        'created_at',
    ];
}
