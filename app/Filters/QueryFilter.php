<?php
namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * QueryFilter – Clase base para filtros dinámicos.
 *
 * Cada filtro concreto extiende esta clase y declara $allowedFilters
 * con los campos permitidos, luego implementa un método por filtro.
 *
 * Uso en Controller:
 *   public function index(MiModeloFilter $filter) {
 *       $data = MiModelo::filter($filter)->get();
 *   }
 */
abstract class QueryFilter
{
    protected Builder $builder;
    protected array $allowedFilters = [];

    public function __construct(protected Request $request) {}

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->request->all() as $key => $value) {
            if (in_array($key, $this->allowedFilters) && method_exists($this, $key)) {
                $this->$key((string) $value);
            }
        }

        return $this->builder;
    }
}
