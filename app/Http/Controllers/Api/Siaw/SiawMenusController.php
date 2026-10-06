<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\HandlesIndexResponse;
use App\Http\Requests\Siaw\Menus\StoreMenusRequest;
use App\Http\Requests\Siaw\Menus\UpdateMenusRequest;
use App\Http\Resources\Siaw\SiawMenusResource;
use App\Http\Resources\Siaw\SiawMenusTinyResource;
use App\Models\dbsiaw\SiawMenus;
use App\Services\Siaw\SiawMenusService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="siaw_menus", description="Menús")
 */
class SiawMenusController extends Controller
{
    use ApiResponse, HandlesIndexResponse;

    public function __construct(private readonly SiawMenusService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_menus",
     *      tags={"siaw_menus"},
     *      summary="Árbol de navegación para el sidebar Angular/PrimeNG",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", description="true devuelve la respuesta paginada con `meta`", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="tiny", in="query", description="true devuelve SiawMenusTinySchema", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="sistema_id", in="query", description="Filtro exacto por sistema_id", @OA\Schema(type="string", format="uuid")),
     *      @OA\Parameter(name="parent_id", in="query", description="Filtro exacto por parent_id", @OA\Schema(type="string", format="uuid")),
     *      @OA\Parameter(name="activo", in="query", description="Filtro exacto por activo", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="dashboard", in="query", description="Filtro exacto por dashboard", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="search", in="query", description="Búsqueda de texto libre", @OA\Schema(type="string")),
     *      @OA\Parameter(name="sorts", in="query", description="Orden: campo o -campo (descendente)", @OA\Schema(type="string")),
     *      @OA\Parameter(name="include", in="query", description="Relaciones a cargar, separadas por coma (ej. hijos,permisos)", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response=200,
     *          description="Menús registrados",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Menús obtenidos correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawMenusSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $paginate = $request->boolean('paginate');

        $data = $this->service->index($paginate);

        $resource = $request->boolean('tiny')
            ? SiawMenusTinyResource::class
            : SiawMenusResource::class;

        return $this->responseIndex($data, $resource, $paginate, 'Menús obtenidos correctamente');
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_menus/{siaw_menu}",
     *      tags={"siaw_menus"},
     *      summary="Ver un menú por UUID",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_menu", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Menú encontrado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Menú encontrado"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawMenusSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="No encontrado")
     * )
     */
    public function show(SiawMenus $siaw_menu): JsonResponse
    {
        $model = $this->service->show($siaw_menu);

        return $this->responseSuccess('Menú encontrado', new SiawMenusResource($model));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_menus",
     *      tags={"siaw_menus"},
     *      summary="Registrar un menú",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"sistema_id","titulo"},
     *              @OA\Property(property="sistema_id", type="string", format="uuid"),
     *              @OA\Property(property="parent_id", type="string", format="uuid", nullable=true, description="Menú padre, para submenús"),
     *              @OA\Property(property="titulo", type="string", maxLength=100),
     *              @OA\Property(property="descripcion", type="string", maxLength=255, nullable=true),
     *              @OA\Property(property="ruta", type="string", maxLength=255, nullable=true),
     *              @OA\Property(property="nombre_icon", type="string", maxLength=100, nullable=true),
     *              @OA\Property(property="orden", type="integer", minimum=0),
     *              @OA\Property(property="activo", type="boolean"),
     *              @OA\Property(property="dashboard", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Menú creado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Menú creado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawMenusSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(StoreMenusRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Menú creado correctamente', new SiawMenusResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_menus/{siaw_menu}",
     *      tags={"siaw_menus"},
     *      summary="Actualizar un menú",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_menu", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="sistema_id", type="string", format="uuid"),
     *              @OA\Property(property="parent_id", type="string", format="uuid", nullable=true),
     *              @OA\Property(property="titulo", type="string", maxLength=100),
     *              @OA\Property(property="descripcion", type="string", maxLength=255, nullable=true),
     *              @OA\Property(property="ruta", type="string", maxLength=255, nullable=true),
     *              @OA\Property(property="nombre_icon", type="string", maxLength=100, nullable=true),
     *              @OA\Property(property="orden", type="integer", minimum=0),
     *              @OA\Property(property="activo", type="boolean"),
     *              @OA\Property(property="dashboard", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Menú actualizado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Menú actualizado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawMenusSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function update(UpdateMenusRequest $request, SiawMenus $siaw_menu): JsonResponse
    {
        $model = $this->service->update($siaw_menu, $request->validated());

        return $this->responseSuccess('Menú actualizado correctamente', new SiawMenusResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_menus/{siaw_menu}",
     *      tags={"siaw_menus"},
     *      summary="Eliminar un menú",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_menu", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Menú eliminado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Menú eliminado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawMenusSchema")
     *          )
     *      )
     * )
     */
    public function destroy(SiawMenus $siaw_menu): JsonResponse
    {
        $deleted = $this->service->destroy($siaw_menu);

        return $this->responseSuccess('Menú eliminado correctamente', new SiawMenusResource($deleted));
    }
}
