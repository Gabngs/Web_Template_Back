<?php

namespace App\Services\Siaw;

use App\Models\dbsiaw\SiawSistemas;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiawSistemasService extends AbstractModuleService
{
    protected array $uuidMapping = [];

    public function __construct(protected CrudService $crud) {}

    public function index(bool $paginate = false): mixed
    {
        $query = SiawSistemas::useFilters()->orderBy('descripcion');
        return $paginate ? $query->dynamicPaginate() : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load(['menus']);
    }

    public function store(array $data): Model
    {
        $data['id'] = Str::uuid()->toString();
        return $this->crud->create(SiawSistemas::class, $data, 'crear_siaw_sistema');
    }

    public function update(Model $model, array $data): Model
    {
        return $this->crud->update($model, $data, 'actualizar_siaw_sistema');
    }

    public function destroy(Model $model): Model
    {
        return $this->crud->delete($model, 'eliminar_siaw_sistema');
    }
}
