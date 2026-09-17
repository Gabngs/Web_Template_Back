<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;
use App\Models\dbsiaw\SiawSistemas;
use App\Models\dbsiaw\SiawMenus;
class SiawMenusFilters extends QueryFilters
{
    protected array $allowedFilters  = ['activo', 'dashboard'];
    protected array $allowedSorts    = ['orden', 'titulo', 'created_at'];
    protected array $allowedIncludes = ['sistema', 'parent', 'hijos', 'permisos'];
    protected array $columnSearch    = ['titulo', 'descripcion'];


    protected function sistema_id($value)
    {
        $sistemaPkid = SiawSistemas::where('id', $value)->value('pkid');
        return $this->builder->where('sistema_id', $sistemaPkid);
    }

    protected function parent_id($value)
    {
        $parentPkid = SiawMenus::where('id', $value)->value('pkid');
        return $this->builder->where('parent_id', $parentPkid);
    }
}
