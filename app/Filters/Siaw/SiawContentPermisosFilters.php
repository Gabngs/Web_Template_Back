<?php

namespace App\Filters\Siaw;

use Essa\APIToolKit\Filters\QueryFilters;
use App\Models\dbsiaw\SiawContentModel;
use App\Models\dbsiaw\SiawSistemas;

class SiawContentPermisosFilters extends QueryFilters
{
    protected array $allowedFilters  = ['codename'];
    protected array $allowedSorts    = ['codename', 'created_at'];
    protected array $allowedIncludes = ['contentModel', 'sistema'];
    protected array $columnSearch    = ['codename', 'desc'];

    public function content_model_id($value)
    {
        $cmpkid = SiawContentModel::where('id', $value)->value('pkid');
        return $this->builder->where('content_model_id', $cmpkid);
    }

    /**
     * Filtra por sistema (UUID). Sin este parámetro se listan todos los
     * permisos, incluidos los que aún no tienen sistema asignado, para que
     * ninguno quede oculto.
     */
    public function sistema_id($value)
    {
        $pkid = SiawSistemas::where('id', $value)->value('pkid');
        return $this->builder->where('sistema_id', $pkid);
    }

}
