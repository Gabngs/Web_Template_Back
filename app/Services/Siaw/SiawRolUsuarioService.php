<?php

namespace App\Services\Siaw;

use App\Models\dbsiaw\SiawRolUsuario;
use App\Models\dbsiaw\SiawRoles;
use App\Models\dbsiaw\SiawUsuarios;
use App\Services\AbstractModuleService;
use App\Services\CrudService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SiawRolUsuarioService extends AbstractModuleService
{
    protected array $uuidMapping = [
        'usuario_id' => SiawUsuarios::class,
        'rol_id'     => SiawRoles::class,
    ];

    public function __construct(protected CrudService $crud) {}

    public function index(bool $paginate = false): mixed
    {
        $query = SiawRolUsuario::useFilters()->with(['rol', 'usuario'])->orderByDesc('created_at');
        return $paginate ? $query->dynamicPaginate() : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load(['rol', 'usuario']);
    }

    public function store(array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        $data['id'] = Str::uuid()->toString();
        return $this->crud->create(SiawRolUsuario::class, $data, 'asignar_rol_usuario');
    }

    public function update(Model $model, array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        return $this->crud->update($model, $data, 'actualizar_rol_usuario');
    }

    public function destroy(Model $model): Model
    {
        return $this->crud->delete($model, 'eliminar_rol_usuario');
    }


    public function sync(string $usuarioUuid, array $rolUuids): mixed
    {
        $usuarioPkid = SiawUsuarios::where('id', $usuarioUuid)->value('pkid');
        $rolPkids    = SiawRoles::whereIn('id', $rolUuids)->pluck('pkid')->map(fn ($v) => (int) $v)->all();

        $actuales = SiawRolUsuario::where('usuario_id', $usuarioPkid)->get()->keyBy('rol_id');

        foreach ($actuales as $rolPkid => $registro) {
            if (!in_array((int) $rolPkid, $rolPkids, true)) {
                $this->crud->delete($registro, 'eliminar_rol_usuario');
            }
        }

        foreach ($rolPkids as $rolPkid) {
            if (!$actuales->has($rolPkid)) {
                $this->crud->create(SiawRolUsuario::class, [
                    'id'         => Str::uuid()->toString(),
                    'usuario_id' => $usuarioPkid,
                    'rol_id'     => $rolPkid,
                ], 'asignar_rol_usuario');
            }
        }

        return SiawRolUsuario::where('usuario_id', $usuarioPkid)->with(['rol', 'usuario'])->get();
    }
}
