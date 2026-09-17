<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;
use App\Models\dbsiaw\SiawRoles;

class SiawUsuariosFilters extends QueryFilters
{
    protected array $allowedFilters  = ['activo'];
    protected array $allowedSorts    = ['nombre', 'email', 'codigo', 'created_at'];
    protected array $allowedIncludes = ['rol'];
    protected array $columnSearch    = ['nombre', 'apellidos', 'email', 'codigo'];


    public function rol_id($value)
    {
       $rolPkid = SiawRoles::where('id', $value)->value('pkid');
       return $this->builder->where('rol_id', $rolPkid);
    }
}
