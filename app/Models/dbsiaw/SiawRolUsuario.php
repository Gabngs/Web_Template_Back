<?php

namespace App\Models\dbsiaw;

use App\Filters\Siaw\SiawRolUsuarioFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiawRolUsuario extends Model
{
    use SoftDeletes, Filterable;

    protected $connection  = 'dbsiaw';
    protected $table       = 'siaw_rol_usuario';
    protected $primaryKey  = 'id';
    public    $incrementing = false;
    protected $keyType     = 'string';

    protected string $default_filters = SiawRolUsuarioFilters::class;

    protected $fillable = [
        'id',
        'usuario_id',
        'rol_id',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',
    ];

    public function rol()
    {
        return $this->belongsTo(SiawRoles::class, 'rol_id', 'pkid');
    }
    public function usuario()
    {
        return $this->belongsTo(SiawUsuarios::class, 'usuario_id', 'pkid');
    }
    public function created_by()
    {
        return $this->belongsTo(SiawUsuarios::class, 'created_by_id', 'pkid');
    }
    public function updated_by()
    {
        return $this->belongsTo(SiawUsuarios::class, 'updated_by_id', 'pkid');
    }
    public function deleted_by()
    {
        return $this->belongsTo(SiawUsuarios::class, 'deleted_by_id', 'pkid');
    }
}
