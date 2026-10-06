<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;

class SiawRolesFilters extends QueryFilters
{
    protected array $columnSearch = [
        'name',
        'slug',
        'descripcion',
    ];

    protected array $allowedFilters = [
        'name',
        'slug',
        'guard_name',
        'activo',
    ];

    protected array $allowedIncludes = [
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected array $allowedSorts = [
        'name',
        'slug',
        'activo',
        'created_at',
        'updated_at',
    ];
}
