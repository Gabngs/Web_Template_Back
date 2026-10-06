<?php

namespace App\Filters\Siaw;

use App\Models\dblcs\LcsCatalogoDet;
use App\Models\dbsiaw\SiawSistemas;
use Essa\APIToolKit\Filters\QueryFilters;

class SiawParametrosFilters extends QueryFilters
{
    protected array $columnSearch = [
        'codigo',
        'descripcion',
        'valor',
    ];

    protected array $allowedFilters = [
        'codigo',
        'descripcion',
        'valor',
        'activo',
    ];

    protected array $allowedIncludes = [
        'siaw_sistemas',
        'lcs_catalogo_det',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected array $allowedSorts = [
        'codigo',
        'descripcion',
        'valor',
        'activo',
        'created_at',
        'updated_at',
    ];

    public function sistema_id($value)
    {
        if ($value === null || $value === '') {
            return $this->builder;
        }

        $pkid = SiawSistemas::where('id', $value)->value('pkid');

        return $this->builder->where('sistema_id', $pkid ?? 0);
    }

    public function tipodato_id($value)
    {
        if ($value === null || $value === '') {
            return $this->builder;
        }

        $pkid = LcsCatalogoDet::where('id', $value)->value('pkid');

        return $this->builder->where('tipodato_id', $pkid ?? 0);
    }
}
