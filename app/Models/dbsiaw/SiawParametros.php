<?php

namespace App\Models\dbsiaw;

use App\Models\dblcs\LcsCatalogoDet;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiawParametros extends Model
{
    use SoftDeletes, Filterable;

    protected $connection   = 'dbsiaw';
    protected $table        = 'siaw_parametros';
    protected $primaryKey   = 'id';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'id',
        'sistema_id',
        'codigo',
        'descripcion',
        'valor',
        'tipodato_id',
        'activo',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function siaw_sistemas(): BelongsTo
    {
        return $this->belongsTo(SiawSistemas::class, 'sistema_id', 'pkid');
    }

    // Catálogo 'catalogo_tipodato' vive en la base de negocio (dblcs).
    public function lcs_catalogo_det(): BelongsTo
    {
        return $this->belongsTo(LcsCatalogoDet::class, 'tipodato_id', 'pkid');
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
