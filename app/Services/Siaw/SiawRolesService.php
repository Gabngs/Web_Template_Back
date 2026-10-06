<?php

namespace App\Services\Siaw;

use App\Models\dbsiaw\SiawRoles;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiawRolesService extends AbstractModuleService
{
    public function __construct(protected CrudService $crud) {}

    /**
     * Relaciones eager-loaded en index/show, y en la respuesta de store/update/destroy.
     * Centralizada acá — el Controller nunca decide qué relaciones cargar.
     */
    const RELATIONS = [
        'created_by', 'updated_by', 'deleted_by',
    ];

    /**
     * Mapeo de campos UUID -> PKID.
     * Clave: nombre del campo en $data que llega del frontend (UUID)
     * Valor: clase del modelo donde se busca ese UUID para obtener su pkid
     */
    protected array $uuidMapping = [];

    public function index(bool $paginate = false): mixed
    {
        $query = SiawRoles::useFilters()->with(self::RELATIONS)->orderBy('name');

        return $paginate
            ? $query->dynamicPaginate()
            : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load(self::RELATIONS);
    }

    public function store(array $data): Model
    {
        $data['id'] = Str::uuid()->toString();

        $model = $this->crud->create(
            SiawRoles::class,
            $data,
            'crear_rol',
        );

        return $model->load(self::RELATIONS);
    }

    public function update(Model $model, array $data): Model
    {
        $model = $this->crud->update(
            $model,
            $data,
            'actualizar_rol',
        );

        return $model->load(self::RELATIONS);
    }

    public function destroy(Model $model): Model
    {
        $model->load(self::RELATIONS);

        return $this->crud->delete(
            $model,
            'eliminar_rol',
        );
    }
}
