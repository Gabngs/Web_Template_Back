<?php

namespace App\Models\dbsiaw;

use App\Filters\Siaw\SiawMenuPermisoFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiawMenuPermiso extends Model
{
    use SoftDeletes, Filterable;

    protected $connection  = 'dbsiaw';
    protected $table       = 'siaw_menu_permiso';
    protected $primaryKey  = 'id';
    public    $incrementing = false;
    protected $keyType     = 'string';

    protected string $default_filters = SiawMenuPermisoFilters::class;

    protected $fillable = [
        'id',
        'menu_id',
        'permiso_id',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(SiawMenus::class, 'menu_id', 'pkid');
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
