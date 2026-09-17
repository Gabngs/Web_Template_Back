<?php

namespace App\Services\Siaw;

use App\Models\dbsiaw\SiawContentPermisos;
use App\Models\dbsiaw\SiawPermisoRol;
use App\Models\dbsiaw\SiawRoles;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Asigna/quita permisos a un rol (siaw_permiso_rol). Este es el módulo que
 * responde "¿qué puede hacer el rol X?" — se combina con SiawRolUsuarioService
 * ("¿qué rol tiene el usuario Y?") para resolver el permiso efectivo.
 */
class SiawPermisoRolService extends AbstractModuleService
{
    protected array $uuidMapping = [
        'permiso_id' => SiawContentPermisos::class,
        'rol_id'     => SiawRoles::class,
    ];

    public function __construct(protected CrudService $crud) {}

    public function index(bool $paginate = false): mixed
    {
        $query = SiawPermisoRol::useFilters()->with(['rol', 'permiso'])->orderByDesc('created_at');
        return $paginate ? $query->dynamicPaginate() : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load(['rol', 'permiso']);
    }

    public function store(array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);

        // Idempotente: sumar un permiso que el rol ya tiene NO crea una fila
        // duplicada. Si la dupla existe se devuelve; si estaba soft-deleted se
        // restaura.
        $existente = SiawPermisoRol::withTrashed()
            ->where('rol_id', $data['rol_id'])
            ->where('permiso_id', $data['permiso_id'])
            ->first();

        if ($existente) {
            if ($existente->trashed()) {
                $existente->restore();
            }
            return $existente->load(['rol', 'permiso']);
        }

        $data['id'] = Str::uuid()->toString();
        return $this->crud->create(SiawPermisoRol::class, $data, 'asignar_permiso_rol');
    }

    public function update(Model $model, array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        return $this->crud->update($model, $data, 'actualizar_permiso_rol');
    }

    public function destroy(Model $model): Model
    {
        return $this->crud->delete($model, 'eliminar_permiso_rol');
    }

    /**
     * Reemplaza el set completo de permisos de un rol por el que llega en
     * $permisoUuids — pensado para un selector tipo pick-list (Disponibles/
     * Asignados) donde el frontend manda el estado FINAL, no un diff. Agrega
     * los que faltan y quita (soft-delete) los que ya no están en la lista.
     */
    public function sync(string $rolUuid, array $permisoUuids): mixed
    {
        $rolPkid      = SiawRoles::where('id', $rolUuid)->value('pkid');
        $permisoPkids = SiawContentPermisos::whereIn('id', $permisoUuids)->pluck('pkid')->map(fn ($v) => (int) $v)->all();

        $actuales = SiawPermisoRol::where('rol_id', $rolPkid)->get()->keyBy('permiso_id');

        foreach ($actuales as $permisoPkid => $registro) {
            if (!in_array((int) $permisoPkid, $permisoPkids, true)) {
                $this->crud->delete($registro, 'eliminar_permiso_rol');
            }
        }

        foreach ($permisoPkids as $permisoPkid) {
            if ($actuales->has($permisoPkid)) {
                continue;
            }

            // Si ya hubo una asignación soft-deleted para esta dupla, se
            // restaura en vez de crear una fila nueva (evita duplicados y
            // choca con un índice único si lo hubiera).
            $trashed = SiawPermisoRol::onlyTrashed()
                ->where('rol_id', $rolPkid)
                ->where('permiso_id', $permisoPkid)
                ->first();

            if ($trashed) {
                $trashed->restore();
                continue;
            }

            $this->crud->create(SiawPermisoRol::class, [
                'id'         => Str::uuid()->toString(),
                'rol_id'     => $rolPkid,
                'permiso_id' => $permisoPkid,
            ], 'asignar_permiso_rol');
        }

        return SiawPermisoRol::where('rol_id', $rolPkid)->with(['rol', 'permiso'])->get();
    }
}
