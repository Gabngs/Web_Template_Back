<?php

namespace App\Models\dbsiaw;

use App\Filters\Siaw\SiawAuditLogFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiawAuditLog extends Model
{
    use Filterable;

    public const TIPO_INSERCION      = 1;
    public const TIPO_ACTUALIZACION  = 2;
    public const TIPO_ELIMINACION    = 3;
    public const TIPO_RESTAURACION   = 4;

    public const ESTADO_EXITO  = 1;
    public const ESTADO_ERROR  = 2;

    public const ORIGEN_MANUAL     = 1;
    public const ORIGEN_AUTOMATICO = 2;

    protected string $default_filters = SiawAuditLogFilters::class;

    // La tabla NO tiene soft delete ni updated_by/deleted_by: es un log inmutable,
    // por eso este modelo no sigue el estándar de SoftDeletes + 3 columnas de auditoría.
    protected $connection  = 'dbsiaw';
    protected $table       = 'siaw_audit_log';
    protected $primaryKey  = 'pkid';
    public    $incrementing = true;

    protected $fillable = [
        'id',
        'nombre_proceso',
        'tipo_proceso',
        'estado_proceso',
        'origen',
        'modelo_afectado',
        'registro_id',
        'input',
        'output',
        'error',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'input'  => 'array',
            'output' => 'array',
        ];
    }

    // PK interna = pkid, pero la API expone y busca por uuid.
    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(SiawUsuarios::class, 'created_by_id', 'pkid');
    }
}
