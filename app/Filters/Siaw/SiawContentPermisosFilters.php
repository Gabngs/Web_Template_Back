<?php

namespace App\Filters\Siaw;

use App\Models\dbsiaw\SiawContentModel;
use App\Models\dbsiaw\SiawSistemas;
use Essa\APIToolKit\Filters\QueryFilters;

class SiawContentPermisosFilters extends QueryFilters
{
    protected array $columnSearch = [
        'codename',
        'desc',
    ];

    protected array $allowedFilters = [
        'codename',
        'desc',
    ];

    protected array $allowedIncludes = [
        'contentModel',
        'sistema',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected array $allowedSorts = [
        'codename',
        'desc',
        'created_at',
        'updated_at',
    ];

    public function content_model_id($value)
    {
        if ($value === null || $value === '') {
            return $this->builder;
        }

        $pkid = SiawContentModel::where('id', $value)->value('pkid');

        return $this->builder->where('content_model_id', $pkid ?? 0);
    }

    /**
     * Filtra por sistema (UUID). Sin este parámetro se listan todos los
     * permisos, incluidos los que aún no tienen sistema asignado, para que
     * ninguno quede oculto.
     */
    public function sistema_id($value)
    {
        if ($value === null || $value === '') {
            return $this->builder;
        }

        $pkid = SiawSistemas::where('id', $value)->value('pkid');

        return $this->builder->where('sistema_id', $pkid ?? 0);
    }
}
