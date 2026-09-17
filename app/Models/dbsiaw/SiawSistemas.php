<?php

namespace App\Models\dbsiaw;

use App\Filters\Siaw\SiawSistemasFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiawSistemas extends Model
{
    use SoftDeletes, Filterable;

    protected $connection  = 'dbsiaw';
    protected $table       = 'siaw_sistemas';
    protected $primaryKey  = 'id';
    public    $incrementing = false;
    protected $keyType     = 'string';

    protected string $default_filters = SiawSistemasFilters::class;

    protected $fillable = [
        'id',
        'codigo',
        'descripcion',
        'activo',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function menus(): HasMany
    {
        return $this->hasMany(SiawMenus::class, 'sistema_id', 'pkid');
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
