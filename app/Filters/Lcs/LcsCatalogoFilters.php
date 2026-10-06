<?php

namespace App\Filters\Lcs;

use Essa\APIToolKit\Filters\QueryFilters;

class LcsCatalogoFilters extends QueryFilters
{
    protected array $columnSearch = [
        'codigo',
        'nombre',
        'descripcion',
    ];

    protected array $allowedFilters = [
        'codigo',
        'nombre',
        'descripcion',
        'activo',
    ];

    protected array $allowedIncludes = [
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected array $allowedSorts = [
        'codigo',
        'nombre',
        'descripcion',
        'activo',
        'created_at',
        'updated_at',
    ];
}
