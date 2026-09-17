<?php

namespace App\Models\dbsiaw;

use App\Filters\Siaw\SiawContentPermisosFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiawContentPermisos extends Model
{
    use SoftDeletes, Filterable;

    protected $connection  = 'dbsiaw';
    protected $table       = 'siaw_content_permisos';
    protected $primaryKey  = 'id';
    public    $incrementing = false;
    protected $keyType     = 'string';

    protected string $default_filters = SiawContentPermisosFilters::class;

    protected $fillable = [
        'id',
        'content_model_id',
        'codename',
        'desc',
        'sistema_id',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',
    ];

    public function contentModel(): BelongsTo
    {
        return $this->belongsTo(SiawContentModel::class, 'content_model_id', 'pkid');
    }

    public function sistema(): BelongsTo
    {
        return $this->belongsTo(SiawSistemas::class, 'sistema_id', 'pkid');
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
