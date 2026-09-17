<?php

namespace App\Models\dbsiaw;

use Illuminate\Database\Eloquent\Model;

class SiawAuditLog extends Model
{
    public const TIPO_INSERCION      = 1;
    public const TIPO_ACTUALIZACION  = 2;
    public const TIPO_ELIMINACION    = 3;
    public const TIPO_RESTAURACION   = 4;

    public const ESTADO_EXITO  = 1;
    public const ESTADO_ERROR  = 2;

    public const ORIGEN_MANUAL     = 1;
    public const ORIGEN_AUTOMATICO = 2;

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

    public function createdBy()
    {
        return $this->belongsTo(SiawUsuarios::class, 'created_by_id', 'pkid');
    }
}
