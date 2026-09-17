<?php

namespace App\Models\dbsiaw;

use App\Filters\Siaw\SiawContentModelFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiawContentModel extends Model
{
    use SoftDeletes, Filterable;

    protected $connection  = 'dbsiaw';
    protected $table       = 'siaw_content_model';
    protected $primaryKey  = 'id';
    public    $incrementing = false;
    protected $keyType     = 'string';

    protected string $default_filters = SiawContentModelFilters::class;

    protected $fillable = [
        'id',
        'app_label',
        'app_model',
        'nombre_display',
        'sistema_id',
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
    public function permisos(): HasMany
    {
        return $this->hasMany(SiawContentPermisos::class, 'content_model_id', 'pkid');
    }

    public function sistema(): BelongsTo
    {
        return $this->belongsTo(SiawSistemas::class, 'sistema_id', 'pkid');
    }
}
