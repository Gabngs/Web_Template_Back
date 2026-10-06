<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;

class SiawSistemasFilters extends QueryFilters
{
    protected array $columnSearch = [
        'codigo',
        'descripcion',
    ];

    protected array $allowedFilters = [
        'codigo',
        'descripcion',
        'activo',
    ];

    protected array $allowedIncludes = [
        'menus',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected array $allowedSorts = [
        'codigo',
        'descripcion',
        'activo',
        'created_at',
        'updated_at',
    ];
}
