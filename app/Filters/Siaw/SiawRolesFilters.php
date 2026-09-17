<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;

class SiawRolesFilters extends QueryFilters
{
    protected array $allowedFilters  = ['slug', 'activo', 'guard_name'];
    protected array $allowedSorts    = ['name', 'created_at'];
    protected array $allowedIncludes = [];
    protected array $columnSearch    = ['name', 'slug', 'descripcion'];
}
