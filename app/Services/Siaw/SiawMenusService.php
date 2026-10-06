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
    public function __construct(protected CrudService $crud) {}

    /**
     * Relaciones eager-loaded en index/show, y en la respuesta de store/update/destroy.
     * Centralizada acá — el Controller nunca decide qué relaciones cargar.
     * `hijos` y `permisos` no se cargan por defecto: se piden con ?include=hijos (show siempre carga hijos).
     */
    const RELATIONS = [
        'sistema', 'parent',
        'created_by', 'updated_by', 'deleted_by',
    ];

    /**
     * Mapeo de campos UUID -> PKID.
     * Clave: nombre del campo en $data que llega del frontend (UUID)
     * Valor: clase del modelo donde se busca ese UUID para obtener su pkid
     */
    protected array $uuidMapping = [
        'sistema_id' => SiawSistemas::class,
        'parent_id'  => SiawMenus::class,
    ];

    public function index(bool $paginate = false): mixed
    {
        $query = SiawMenus::useFilters()->with(self::RELATIONS)->orderBy('orden');

        return $paginate
            ? $query->dynamicPaginate()
            : $query->get();
    }

    public function show(Model $model): Model
    {
        return $model->load([...self::RELATIONS, 'hijos']);
    }

    public function store(array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);
        $data['id'] = Str::uuid()->toString();

        /** @var SiawMenus $model */
        $model = $this->crud->create(
            SiawMenus::class,
            $data,
            'crear_siaw_menu',
        );

        $model->refresh();

        $model->clave = $this->calcularClave($model->pkid, $model->parent_id);
        $model->save();

        return $model->load(self::RELATIONS);
    }

    public function update(Model $model, array $data): Model
    {
        $this->crud->mapUuidsToPkids($data, $this->uuidMapping);

        $model = $this->crud->update(
            $model,
            $data,
            'actualizar_siaw_menu',
        );

        if (array_key_exists('parent_id', $data)) {
            $model->clave = $this->calcularClave($model->pkid, $model->parent_id);
            $model->save();
        }

        return $model->load(self::RELATIONS);
    }

    public function destroy(Model $model): Model
    {
        $model->load(self::RELATIONS);

        return $this->crud->delete(
            $model,
            'eliminar_siaw_menu',
        );
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
