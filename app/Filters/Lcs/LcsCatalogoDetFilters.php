<?php

namespace App\Filters\Lcs;

use App\Models\dblcs\LcsCatalogo;
use Essa\APIToolKit\Filters\QueryFilters;

class LcsCatalogoDetFilters extends QueryFilters
{
    protected array $columnSearch = [
        'codigo',
        'abreviatura',
        'nombre',
        'descripcion',
        'valor_texto',
    ];

    protected array $allowedFilters = [
        'codigo',
        'abreviatura',
        'nombre',
        'descripcion',
        'valor_numerico',
        'valor_texto',
        'activo',
    ];

    protected array $allowedIncludes = [
        'lcs_catalogo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected array $allowedSorts = [
        'codigo',
        'abreviatura',
        'nombre',
        'descripcion',
        'valor_numerico',
        'valor_texto',
        'activo',
        'created_at',
        'updated_at',
    ];

    public function catalogo_id($value)
    {
        if ($value === null || $value === '') {
            return $this->builder;
        }

        $pkid = LcsCatalogo::where('id', $value)->value('pkid');

        return $this->builder->where('catalogo_id', $pkid ?? 0);
    }
}
