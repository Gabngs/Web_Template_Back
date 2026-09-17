<?php

namespace App\Services\Siaw;

use App\Models\dbsiaw\SiawMenus;
use App\Models\dbsiaw\SiawSistemas;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiawMenusService extends AbstractModuleService
{
    protected array $uuidMapping = [
        'sistema_id' => SiawSistemas::class,
        'parent_id'  => SiawMenus::class,
    ];

    public function __construct(protected CrudService $crud) {}

    public function index(bool $paginate = false): mixed
    {
        $query = SiawMenus::useFilters()->orderBy('orden');
        return $paginate ? $query->dynamicPaginate() : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load(['sistema', 'parent', 'hijos']);
    }

    public function store(array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        $data['id'] = Str::uuid()->toString();

        /** @var SiawMenus $model */
        $model = $this->crud->create(SiawMenus::class, $data, 'crear_siaw_menu');


        $model->refresh();

        $model->clave = $this->calcularClave($model->pkid, $model->parent_id);
        $model->save();

        return $model;
    }

    public function update(Model $model, array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);

        $model = $this->crud->update($model, $data, 'actualizar_siaw_menu');

        if (array_key_exists('parent_id', $data)) {
            $model->clave = $this->calcularClave($model->pkid, $model->parent_id);
            $model->save();
        }

        return $model;
    }

    public function destroy(Model $model): Model
    {
        return $this->crud->delete($model, 'eliminar_siaw_menu');
    }

    private function calcularClave(int $pkid, ?int $parentPkid): string
    {
        if (!$parentPkid) {
            return (string) $pkid;
        }

        $parentClave = SiawMenus::where('pkid', $parentPkid)->value('clave');

        return $parentClave ? "{$parentClave}-{$pkid}" : (string) $pkid;
    }
}
