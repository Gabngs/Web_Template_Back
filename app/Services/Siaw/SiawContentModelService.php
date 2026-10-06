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
    public function __construct(protected CrudService $crud) {}

    /**
     * Relaciones eager-loaded en index/show, y en la respuesta de store/update/destroy.
     * Centralizada acá — el Controller nunca decide qué relaciones cargar.
     * `permisos` no se carga por defecto en el listado: ?with_permisos=true o ?include=permisos.
     */
    const RELATIONS = [
        'sistema',
        'created_by', 'updated_by', 'deleted_by',
    ];

    /**
     * Mapeo de campos UUID -> PKID.
     * Clave: nombre del campo en $data que llega del frontend (UUID)
     * Valor: clase del modelo donde se busca ese UUID para obtener su pkid
     */
    protected array $uuidMapping = [
        'sistema_id' => SiawSistemas::class,
    ];

    public function index(bool $paginate = false): mixed
    {
        $query = SiawContentModel::useFilters()->with(self::RELATIONS)->orderBy('app_label');

        if (request()->boolean('with_permisos')) {
            $query->with('permisos');
        }

        return $paginate
            ? $query->dynamicPaginate()
            : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load([...self::RELATIONS, 'permisos']);
    }

    public function store(array $data): Model
    {
        $data['id'] = Str::uuid()->toString();
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);

        $model = $this->crud->create(
            SiawContentModel::class,
            $data,
            'crear_content_model',
        );

        return $model->load(self::RELATIONS);
    }

    public function update(Model $model, array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);

        $model = $this->crud->update(
            $model,
            $data,
            'actualizar_content_model',
        );

        return $model->load(self::RELATIONS);
    }

    public function destroy(Model $model): Model
    {
        $model->load(self::RELATIONS);

        return $this->crud->delete(
            $model,
            'eliminar_content_model',
        );
    }
}
