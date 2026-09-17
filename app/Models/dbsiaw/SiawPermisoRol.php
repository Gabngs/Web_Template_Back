<?php

namespace App\Models\dbsiaw;

use App\Filters\Siaw\SiawPermisoRolFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiawPermisoRol extends Model
{
    use SoftDeletes, Filterable;

    protected $connection  = 'dbsiaw';
    protected $table       = 'siaw_permiso_rol';
    protected $primaryKey  = 'id';
    public    $incrementing = false;
    protected $keyType     = 'string';

    protected string $default_filters = SiawPermisoRolFilters::class;

    protected $fillable = [
        'id',
        'permiso_id',
        'rol_id',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',
    ];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(SiawRoles::class, 'rol_id', 'pkid');
    }

    public function permiso(): BelongsTo
    {
        return $this->belongsTo(SiawContentPermisos::class, 'permiso_id', 'pkid');
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
