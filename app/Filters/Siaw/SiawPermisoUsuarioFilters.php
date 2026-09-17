<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;
use App\Models\dbsiaw\SiawContentPermisos;
use App\Models\dbsiaw\SiawUsuarios;
class SiawPermisoUsuarioFilters extends QueryFilters
{
    protected array $allowedFilters  = ['permitido'];
    protected array $allowedSorts    = ['created_at'];
    protected array $allowedIncludes = ['usuario', 'permiso'];
    protected array $columnSearch    = [];

    protected function permiso_id($value)
    {
        $permisoPkid = SiawContentPermisos::where('id', $value)->value('pkid');
        return $this->builder->where('permiso_id', $permisoPkid);
    }

    protected function usuario_id($value)
    {
        $usuarioPkid = SiawUsuarios::where('id', $value)->value('pkid');
        return $this->builder->where('usuario_id', $usuarioPkid);
    }
}
