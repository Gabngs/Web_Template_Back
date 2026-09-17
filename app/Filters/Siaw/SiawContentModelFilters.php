<?php

namespace App\Filters\Siaw;

use App\Models\dbsiaw\SiawSistemas;
use Essa\APIToolKit\Filters\QueryFilters;

class SiawContentModelFilters extends QueryFilters
{
    protected array $allowedFilters  = ['app_label', 'app_model'];
    protected array $allowedSorts    = ['app_label', 'app_model', 'created_at'];
    protected array $allowedIncludes = ['permisos', 'sistema'];
    protected array $columnSearch    = ['app_label', 'app_model', 'nombre_display'];

    /**
     * Filtra por sistema (UUID). Sin este parámetro se listan todos los
     * modelos, incluidos los que no tienen sistema asignado.
     */
    public function sistema_id($value)
    {
        $pkid = SiawSistemas::where('id', $value)->value('pkid');
        return $this->builder->where('sistema_id', $pkid);
    }
}
