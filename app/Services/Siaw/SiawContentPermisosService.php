<?php

namespace App\Services\Siaw;

use App\Models\dbsiaw\SiawContentModel;
use App\Models\dbsiaw\SiawContentPermisos;
use App\Models\dbsiaw\SiawPermisoRol;
use App\Models\dbsiaw\SiawRoles;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiawContentPermisosService extends AbstractModuleService
{
    // Roles que reciben AUTOMÁTICAMENTE todo permiso nuevo — misma convención
    // que usan los seeders (whereIn slug ['isSuperUser', 'isAdmin']).
    private const ROLES_SUPER = ['isSuperUser', 'isAdmin'];

    protected array $uuidMapping = [
        'content_model_id' => SiawContentModel::class,
    ];

    public function __construct(protected CrudService $crud) {}

    public function index(bool $paginate = false): mixed
    {
        $query = SiawContentPermisos::useFilters()->orderBy('codename');

        if (request('app_model')) {
            $query->whereHas('contentModel', fn($q) => $q->where('app_model', request('app_model')));
        }

        if (request()->boolean('with_model')) {
            $query->with('contentModel');
        }

        return $paginate ? $query->dynamicPaginate() : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load(['contentModel']);
    }

    public function store(array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        $data['sistema_id'] = $this->sistemaDelModelo($data['content_model_id'] ?? null);
        $permiso = $this->crud->create(SiawContentPermisos::class, $data, 'crear_permiso');

        $this->asignarARolesSuper([$permiso->pkid]);

        return $permiso;
    }

    public function update(Model $model, array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        return $this->crud->update($model, $data, 'actualizar_permiso');
    }

    public function destroy(Model $model): Model
    {
        return $this->crud->delete($model, 'eliminar_permiso');
    }

    public function bulkCreate(array $data): array
    {
        // Preparar permisos agregando el content_model_id a cada uno
        $permisos = array_map(function ($permiso) use ($data) {
            $permiso['content_model_id'] = $data['content_model_id'];
            $permiso['id'] = Str::uuid()->toString();
            return $permiso;
        }, $data['permisos']);

        // Mapear todos los UUIDs a PKIDs en una sola operación (más eficiente)
        $this->crud->mapUuidsToPkidsBulk($permisos, $this->uuidMapping);

        // Todos los permisos del bulk cuelgan del mismo content model → un solo lookup.
        $sistemaId = $this->sistemaDelModelo($permisos[0]['content_model_id'] ?? null);

        // Crear cada permiso individualmente con auditoría
        $createdModels = [];
        foreach ($permisos as $permisoData) {
            $permisoData['sistema_id'] = $sistemaId;
            $createdModels[] = $this->crud->create(SiawContentPermisos::class, $permisoData, 'crear_permiso');
        }

        $this->asignarARolesSuper(array_map(fn ($m) => $m->pkid, $createdModels));

        return $createdModels;
    }

    /**
     * El permiso hereda el sistema de su content model. Si el permiso no tiene
     * modelo, o el modelo aún no tiene sistema asignado, queda en null (y el
     * filtro por sistema lo sigue mostrando cuando no hay selección).
     */
    private function sistemaDelModelo($contentModelPkid): ?int
    {
        if (empty($contentModelPkid)) {
            return null;
        }

        return SiawContentModel::where('pkid', $contentModelPkid)->value('sistema_id');
    }

    /**
     * Vincula cada permiso recién creado a los roles "super" (isSuperUser /
     * isAdmin) en siaw_permiso_rol. Idempotente: si la dupla ya existe la
     * ignora, y si estaba soft-deleted la restaura — nunca crea filas
     * duplicadas. Así el superusuario siempre tiene TODOS los permisos sin
     * depender de que alguien lo sincronice a mano.
     */
    private function asignarARolesSuper(array $permisoPkids): void
    {
        $permisoPkids = array_values(array_filter($permisoPkids));
        if (empty($permisoPkids)) {
            return;
        }

        $rolPkids = SiawRoles::whereIn('slug', self::ROLES_SUPER)->pluck('pkid');

        foreach ($rolPkids as $rolPkid) {
            foreach ($permisoPkids as $permisoPkid) {
                $existente = SiawPermisoRol::withTrashed()
                    ->where('rol_id', $rolPkid)
                    ->where('permiso_id', $permisoPkid)
                    ->first();

                if ($existente) {
                    if ($existente->trashed()) {
                        $existente->restore();
                    }
                    continue;
                }

                $this->crud->create(SiawPermisoRol::class, [
                    'id'         => Str::uuid()->toString(),
                    'rol_id'     => $rolPkid,
                    'permiso_id' => $permisoPkid,
                ], 'asignar_permiso_rol');
            }
        }
    }
}
