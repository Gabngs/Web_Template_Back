<?php

namespace App\Services\Siaw;

use App\Models\dbsiaw\SiawRoles;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiawRolesService extends AbstractModuleService
{
    protected array $uuidMapping = [];

    public function __construct(protected CrudService $crud) {}

    public function index(bool $paginate = false): mixed
    {
        $query = SiawRoles::useFilters()->orderBy('name');
        return $paginate ? $query->dynamicPaginate() : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model;
    }

    public function store(array $data): Model
    {
        $data['id'] = Str::uuid()->toString();
        return $this->crud->create(SiawRoles::class, $data, 'crear_rol');
    }

    public function update(Model $model, array $data): Model
    {
        return $this->crud->update($model, $data, 'actualizar_rol');
    }

    public function destroy(Model $model): Model
    {
        return $this->crud->delete($model, 'eliminar_rol');
    }
}
