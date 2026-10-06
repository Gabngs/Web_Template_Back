<?php

namespace App\Models\dblcs;

use App\Models\dbsiaw\SiawUsuarios;
use App\Filters\Lcs\LcsCatalogoDetFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LcsCatalogoDet extends Model
{
    use SoftDeletes, Filterable;

    protected string $default_filters = LcsCatalogoDetFilters::class;

    protected $connection   = 'dblcs';
    protected $table        = 'lcs_catalogo_det';
    protected $primaryKey   = 'id';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'id',
        'catalogo_id',
        'codigo',
        'abreviatura',
        'nombre',
        'descripcion',
        'valor_numerico',
        'valor_texto',
        'activo',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',
    ];

    protected $casts = [
        'valor_numerico' => 'decimal:6',
        'activo' => 'boolean',
    ];

    public function lcs_catalogo(): BelongsTo
    {
        return $this->belongsTo(LcsCatalogo::class, 'catalogo_id', 'pkid');
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(SiawUsuarios::class, 'created_by_id', 'pkid');
    }

    public function updated_by(): BelongsTo
    {
        return $this->belongsTo(SiawUsuarios::class, 'updated_by_id', 'pkid');
    }

    public function deleted_by(): BelongsTo
    {
        return $this->belongsTo(SiawUsuarios::class, 'deleted_by_id', 'pkid');
    }
}
