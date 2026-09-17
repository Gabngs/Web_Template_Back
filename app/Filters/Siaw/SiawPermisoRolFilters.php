<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;
use App\Models\dbsiaw\SiawContentPermisos;
use App\Models\dbsiaw\SiawRoles;

class SiawPermisoRolFilters extends QueryFilters
{
    protected array $allowedFilters  = [];
    protected array $allowedSorts    = ['created_at'];
    protected array $allowedIncludes = ['rol', 'permiso'];
    protected array $columnSearch    = [];

    protected function permiso_id($value)
    {
        $permisoPkid = SiawContentPermisos::where('id', $value)->value('pkid');
        return $this->builder->where('permiso_id', $permisoPkid);
    }

    protected function rol_id($value)
    {
        $rolPkid = SiawRoles::where('id', $value)->value('pkid');
        return $this->builder->where('rol_id', $rolPkid);
    }
}
