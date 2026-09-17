<?php

namespace App\Models\dbsiaw;

use App\Filters\Siaw\SiawMenusFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiawMenus extends Model
{
    use SoftDeletes, Filterable;

    protected $connection  = 'dbsiaw';
    protected $table       = 'siaw_menus';
    protected $primaryKey  = 'id';
    public    $incrementing = false;
    protected $keyType     = 'string';

    protected string $default_filters = SiawMenusFilters::class;

    // "clave" NO está en fillable a propósito: la asigna el Service después del
    // store() (necesita el pkid autoincrement) mediante asignación directa de
    // atributo + save(), que no pasa por mass-assignment.
    protected $fillable = [
        'id',
        'sistema_id',
        'parent_id',
        'titulo',
        'descripcion',
        'ruta',
        'nombre_icon',
        'orden',
        'activo',
        'dashboard',
        'created_by_id',
        'updated_by_id',
        'deleted_by_id',
    ];

    protected $casts = [
        'activo'    => 'boolean',
        'dashboard' => 'boolean',
        'orden'     => 'integer',
    ];

    public function sistema(): BelongsTo
    {
        return $this->belongsTo(SiawSistemas::class, 'sistema_id', 'pkid');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SiawMenus::class, 'parent_id', 'pkid');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(SiawMenus::class, 'parent_id', 'pkid')->orderBy('orden');
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(SiawMenuPermiso::class, 'menu_id', 'pkid');
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
