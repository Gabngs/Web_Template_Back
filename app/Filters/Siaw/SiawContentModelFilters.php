<?php

namespace App\Filters\Siaw;

use App\Models\dbsiaw\SiawSistemas;
use Essa\APIToolKit\Filters\QueryFilters;

class SiawContentModelFilters extends QueryFilters
{
    protected array $columnSearch = [
        'app_label',
        'app_model',
        'nombre_display',
    ];

    protected array $allowedFilters = [
        'app_label',
        'app_model',
        'nombre_display',
    ];

    protected array $allowedIncludes = [
        'permisos',
        'sistema',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected array $allowedSorts = [
        'app_label',
        'app_model',
        'nombre_display',
        'created_at',
        'updated_at',
    ];

    /**
     * Filtra por sistema (UUID). Sin este parámetro se listan todos los
     * modelos, incluidos los que no tienen sistema asignado.
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
