<?php

namespace App\Models\dblcs;

use App\Models\dbsiaw\SiawUsuarios;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LcsCatalogo extends Model
{
    use SoftDeletes, Filterable;

    // pkid estable de los catálogos sembrados (ver LcsCatalogoSeeder).
    public const TIPODATO = 1;

    protected $connection   = 'dblcs';
    protected $table        = 'lcs_catalogo';
    protected $primaryKey   = 'id';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'id',
        'codigo',
        'nombre',
        'descripcion',
        'activo',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(LcsCatalogoDet::class, 'catalogo_id', 'pkid');
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
