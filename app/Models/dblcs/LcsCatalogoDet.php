<?php

namespace App\Models\dblcs;

use App\Models\dbsiaw\SiawUsuarios;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LcsCatalogoDet extends Model
{
    use SoftDeletes, Filterable;

    // pkid estable de los valores de 'catalogo_tipodato' (ver LcsCatalogoSeeder).
    public const TIPODATO_STRING   = 1;
    public const TIPODATO_INT      = 2;
    public const TIPODATO_DECIMAL  = 3;
    public const TIPODATO_BOOLEAN  = 4;
    public const TIPODATO_DATE     = 5;
    public const TIPODATO_TIME     = 6;
    public const TIPODATO_DATETIME = 7;
    public const TIPODATO_JSON     = 8;

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

    public function catalogo(): BelongsTo
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
