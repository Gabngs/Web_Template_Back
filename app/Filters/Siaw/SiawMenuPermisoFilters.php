<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;
use App\Models\dbsiaw\SiawMenus;
use App\Models\dbsiaw\SiawContentPermisos;

class SiawMenuPermisoFilters extends QueryFilters
{
    protected array $allowedFilters  = [];
    protected array $allowedSorts    = ['created_at'];
    protected array $allowedIncludes = ['menu', 'permiso'];
    protected array $columnSearch    = [];


    protected function menu_id($value)
    {
        $menuPkid = SiawMenus::where('id', $value)->value('pkid');
        return $this->builder->where('menu_id', $menuPkid);
    }

    protected function permiso_id($value)
    {
        $permisoPkid = SiawContentPermisos::where('id', $value)->value('pkid');
        return $this->builder->where('permiso_id', $permisoPkid);
    }
}
