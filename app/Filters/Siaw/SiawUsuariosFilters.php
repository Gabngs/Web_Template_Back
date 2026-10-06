<?php

namespace App\Filters\Siaw;

use App\Models\dbsiaw\SiawRoles;
use Essa\APIToolKit\Filters\QueryFilters;

class SiawUsuariosFilters extends QueryFilters
{
    protected array $columnSearch = [
        'nombre',
        'apellidos',
        'email',
        'codigo',
    ];

    protected array $allowedFilters = [
        'nombre',
        'apellidos',
        'email',
        'codigo',
        'activo',
    ];

    protected array $allowedIncludes = [
        'rol',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected array $allowedSorts = [
        'nombre',
        'apellidos',
        'email',
        'codigo',
        'activo',
        'created_at',
        'updated_at',
    ];

    public function rol_id($value)
    {
        if ($value === null || $value === '') {
            return $this->builder;
        }

        $pkid = SiawRoles::where('id', $value)->value('pkid');

        return $this->builder->where('rol_id', $pkid ?? 0);
    }
}
