<?php
namespace App\Models\Traits;

use App\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * HasFilters – Agrega el scope filter() a cualquier modelo.
 *
 * Uso:
 *   class MiModelo extends Model {
 *       use HasFilters;
 *   }
 *
 *   // En Controller:
 *   MiModelo::filter($filterInstance)->get();
 */
trait HasFilters
{
    public function scopeFilter(Builder $query, QueryFilter $filter): Builder
    {
        return $filter->apply($query);
    }
}
