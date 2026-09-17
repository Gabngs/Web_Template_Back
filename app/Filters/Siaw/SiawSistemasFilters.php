<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;

class SiawSistemasFilters extends QueryFilters
{
    protected array $allowedFilters  = ['codigo', 'activo'];
    protected array $allowedSorts    = ['codigo', 'descripcion', 'created_at'];
    protected array $allowedIncludes = ['menus'];
    protected array $columnSearch    = ['codigo', 'descripcion'];
}
