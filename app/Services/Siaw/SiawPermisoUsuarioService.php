<?php

namespace App\Services\Siaw;

use App\Models\dbsiaw\SiawContentPermisos;
use App\Models\dbsiaw\SiawPermisoUsuario;
use App\Models\dbsiaw\SiawUsuarios;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class SiawPermisoUsuarioService extends AbstractModuleService
{
    protected array $uuidMapping = [
        'permiso_id' => SiawContentPermisos::class,
        'usuario_id' => SiawUsuarios::class,
    ];

    public function __construct(protected CrudService $crud) {}

    public function index(bool $paginate = false): mixed
    {
        $query = SiawPermisoUsuario::useFilters()->with(['usuario', 'permiso'])->orderByDesc('created_at');

        return $paginate ? $query->dynamicPaginate() : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load(['usuario', 'permiso']);
    }

    public function store(array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        $data['id'] = Str::uuid()->toString();

        return $this->crud->create(SiawPermisoUsuario::class, $data, 'asignar_permiso_usuario');
    }

    public function update(Model $model, array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);

        return $this->crud->update($model, $data, 'actualizar_permiso_usuario');
    }

    public function destroy(Model $model): Model
    {
        return $this->crud->delete($model, 'eliminar_permiso_usuario');
    }

    /**
     * Reemplaza el set de permisos EXPLÍCITAMENTE CONCEDIDOS (permitido=true)
     * de un usuario — mismo patrón pick-list que SiawPermisoRolService::sync().
     * No toca las filas con permitido=false (denegaciones explícitas): un
     * pick-list de un solo balde (Disponibles/Asignados) no puede representar
     * "denegado" a la vez que "concedido", así que ese caso queda fuera de
     * este endpoint y se sigue manejando fila por fila con store/update.
     */
    public function sync(string $usuarioUuid, array $permisoUuids): mixed
    {
        $usuarioPkid  = SiawUsuarios::where('id', $usuarioUuid)->value('pkid');
        $permisoPkids = SiawContentPermisos::whereIn('id', $permisoUuids)->pluck('pkid')->map(fn ($v) => (int) $v)->all();

        $actuales = SiawPermisoUsuario::where('usuario_id', $usuarioPkid)
            ->where('permitido', true)
            ->get()
            ->keyBy('permiso_id');

        foreach ($actuales as $permisoPkid => $registro) {
            if (!in_array((int) $permisoPkid, $permisoPkids, true)) {
                $this->crud->delete($registro, 'eliminar_permiso_usuario');
            }
        }

        foreach ($permisoPkids as $permisoPkid) {
            if (!$actuales->has($permisoPkid)) {
                $this->crud->create(SiawPermisoUsuario::class, [
                    'id'         => Str::uuid()->toString(),
                    'usuario_id' => $usuarioPkid,
                    'permiso_id' => $permisoPkid,
                    'permitido'  => true,
                ], 'asignar_permiso_usuario');
            }
        }

        return SiawPermisoUsuario::where('usuario_id', $usuarioPkid)
            ->where('permitido', true)
            ->with(['usuario', 'permiso'])
            ->get();
    }
}
