<?php

namespace App\Models\dbsiaw;

use App\Filters\Siaw\SiawRolesFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiawRoles extends Model
{
    use SoftDeletes, Filterable;

    protected $connection  = 'dbsiaw';
    protected $table       = 'siaw_roles';
    protected $primaryKey  = 'id';
    public    $incrementing = false;
    protected $keyType     = 'string';

    protected string $default_filters = SiawRolesFilters::class;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'guard_name',
        'descripcion',
        'activo',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',
    ];

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
