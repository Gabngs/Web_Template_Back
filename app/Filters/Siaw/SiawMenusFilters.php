<?php

namespace App\Filters\Siaw;

use App\Models\dbsiaw\SiawMenus;
use App\Models\dbsiaw\SiawSistemas;
use Essa\APIToolKit\Filters\QueryFilters;

class SiawMenusFilters extends QueryFilters
{
    protected array $columnSearch = [
        'titulo',
        'descripcion',
        'ruta',
    ];

    protected array $allowedFilters = [
        'titulo',
        'ruta',
        'activo',
        'dashboard',
    ];

    protected array $allowedIncludes = [
        'sistema',
        'parent',
        'hijos',
        'permisos',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected array $allowedSorts = [
        'orden',
        'titulo',
        'activo',
        'created_at',
        'updated_at',
    ];

    public function sistema_id($value)
    {
        if ($value === null || $value === '') {
            return $this->builder;
        }

        $pkid = SiawSistemas::where('id', $value)->value('pkid');

        return $this->builder->where('sistema_id', $pkid ?? 0);
    }

    public function parent_id($value)
    {
        if ($value === null || $value === '') {
            return $this->builder;
        }

        $pkid = SiawMenus::where('id', $value)->value('pkid');

        return $this->builder->where('parent_id', $pkid ?? 0);
    }
}
