<?php

namespace App\Services\Siaw;

use App\Models\dbsiaw\SiawContentModel;
use App\Models\dbsiaw\SiawSistemas;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiawContentModelService extends AbstractModuleService
{
    protected array $uuidMapping = [
        'sistema_id' => SiawSistemas::class,
    ];

    public function __construct(protected CrudService $crud) {}

    public function index(bool $paginate = false): mixed
    {
        $query = SiawContentModel::useFilters()->orderBy('app_label');

        if (request()->boolean('with_permisos')) {
            $query->with('permisos');
        }

        return $paginate ? $query->dynamicPaginate() : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load(['permisos']);
    }

    public function store(array $data): Model
    {
        $data['id'] = Str::uuid()->toString();
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        return $this->crud->create(SiawContentModel::class, $data, 'crear_content_model');
    }

    public function update(Model $model, array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        return $this->crud->update($model, $data, 'actualizar_content_model');
    }

    public function destroy(Model $model): Model
    {
        return $this->crud->delete($model, 'eliminar_content_model');
    }
}
