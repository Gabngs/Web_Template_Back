<?php

namespace App\Services\Siaw;

use App\Models\dbsiaw\SiawAuditLog;
use Illuminate\Database\Eloquent\Model;
use App\Services\AbstractModuleService;
use App\Services\CrudService;

class SiawAuditLogService extends AbstractModuleService
{
    public function __construct(protected CrudService $crud) {}

    /**
     * Relaciones eager-loaded en index/show, y en la respuesta de store/update/destroy.
     * Centralizada acá — el Controller nunca decide qué relaciones cargar.
     */
    const RELATIONS = [
        'created_by',
    ];

    /**
     * Mapeo de campos UUID -> PKID.
     * Clave: nombre del campo en $data que llega del frontend (UUID)
     * Valor: clase del modelo donde se busca ese UUID para obtener su pkid
     */
    protected array $uuidMapping = [
    ];

    public function index(bool $paginate = false): mixed
    {
        $query = SiawAuditLog::useFilters()->with(self::RELATIONS);

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

        // Sin $proceso: auditar la auditoría generaría un log por cada log (recursión).
        $model = $this->crud->create(
            SiawAuditLog::class,
            $data,
        );

        return $model->load(self::RELATIONS);
    }

    public function update(Model $model, array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);

        $model = $this->crud->update(
            $model,
            $data,
        );

        return $model->load(self::RELATIONS);
    }

    public function destroy(Model $model): Model
    {
        $model->load(self::RELATIONS);

        // No pasa por crud->delete(): éste estampa deleted_by_id (columna inexistente en
        // siaw_audit_log) y la tabla no tiene soft delete -> borrado físico.
        $model->delete();

        return $model;
    }
}
