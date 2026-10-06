<?php

namespace App\Services\Lcs;

use App\Models\dblcs\LcsCatalogoDet;
use App\Models\dblcs\LcsCatalogo;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;

class LcsCatalogoDetService extends AbstractModuleService
{
    public function __construct(protected CrudService $crud) {}

    /**
     * Relaciones eager-loaded en index/show, y en la respuesta de store/update/destroy.
     * Centralizada acá — el Controller nunca decide qué relaciones cargar.
     */
    const RELATIONS = [
        'lcs_catalogo',
        'created_by', 'updated_by', 'deleted_by',
    ];

    /**
     * Mapeo de campos UUID -> PKID.
     * Clave: nombre del campo en $data que llega del frontend (UUID)
     * Valor: clase del modelo donde se busca ese UUID para obtener su pkid
     */
    protected array $uuidMapping = [
        'catalogo_id' => LcsCatalogo::class,
    ];

    public function index(bool $paginate = false): mixed
    {
        $query = LcsCatalogoDet::useFilters()->with(self::RELATIONS);

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
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);

        $model = $this->crud->create(
            LcsCatalogoDet::class,
            $data,
            'crear_catalogo_det',
        );

        return $model->load(self::RELATIONS);
    }

    public function update(Model $model, array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);

        $model = $this->crud->update(
            $model,
            $data,
            'actualizar_catalogo_det',
        );

        return $model->load(self::RELATIONS);
    }

    public function destroy(Model $model): Model
    {
        $model->load(self::RELATIONS);

        return $this->crud->delete(
            $model,
            'eliminar_catalogo_det',
        );
    }
}
