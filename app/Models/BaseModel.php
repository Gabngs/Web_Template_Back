<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * BaseModel – Modelo base para tablas de negocio con UUID + PKID dual-key.
 *
 * - pkid: BIGINT auto-incremental (usado en FKs entre tablas)
 * - id:   UUID v4 generado automáticamente (usado en routing/binding)
 *
 * Uso: extender en modelos de negocio (NO en users, roles, permissions).
 */
abstract class BaseModel extends Model
{
    /**
     * La PK expuesta al exterior es el UUID (id).
     * Las FKs internas apuntan a pkid.
     */
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Generar UUID automáticamente al crear el modelo.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Route model binding usa el UUID.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
