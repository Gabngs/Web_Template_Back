<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;
use App\Models\dbsiaw\SiawRoles;
use App\Models\dbsiaw\SiawUsuarios;

class SiawRolUsuarioFilters extends QueryFilters
{
    protected array $allowedFilters  = [];
    protected array $allowedSorts    = ['created_at'];
    protected array $allowedIncludes = ['rol', 'usuario'];
    protected array $columnSearch    = [];

    protected function usuario_id($value)
    {
        $usuarioPkid = SiawUsuarios::where('id', $value)->value('pkid');
        return $this->builder->where('usuario_id', $usuarioPkid);
    }

    protected function rol_id($value)
    {
        $rolPkid = SiawRoles::where('id', $value)->value('pkid');
        return $this->builder->where('rol_id', $rolPkid);
    }
}
