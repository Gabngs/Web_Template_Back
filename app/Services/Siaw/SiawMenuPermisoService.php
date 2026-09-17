<?php

namespace App\Services\Siaw;

use App\Models\dbsiaw\SiawContentPermisos;
use App\Models\dbsiaw\SiawMenuPermiso;
use App\Models\dbsiaw\SiawMenus;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiawMenuPermisoService extends AbstractModuleService
{
    protected array $uuidMapping = [
        'menu_id'    => SiawMenus::class,
        'permiso_id' => SiawContentPermisos::class,
    ];

    public function __construct(protected CrudService $crud) {}

    public function index(bool $paginate = false): mixed
    {
        $query = SiawMenuPermiso::useFilters()->with(['menu', 'permiso']);
        return $paginate ? $query->dynamicPaginate() : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load(['menu', 'permiso']);
    }

    public function store(array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        $data['id'] = Str::uuid()->toString();

        return $this->crud->create(SiawMenuPermiso::class, $data, 'asignar_menu_permiso');
    }

    public function update(Model $model, array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        return $this->crud->update($model, $data, 'actualizar_menu_permiso');
    }

    public function destroy(Model $model): Model
    {
        return $this->crud->delete($model, 'eliminar_menu_permiso');
    }
}
