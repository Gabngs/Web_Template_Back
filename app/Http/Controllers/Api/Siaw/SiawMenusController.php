<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siaw\SiawMenus\StoreRequest;
use App\Http\Requests\Siaw\SiawMenus\UpdateRequest;
use App\Http\Resources\Siaw\SiawMenusResource;
use App\Models\dbsiaw\SiawMenus;
use App\Services\Siaw\SiawMenusService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Menus")
 */
class SiawMenusController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SiawMenusService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_menus",
     *      tags={"Menus"},
     *      summary="Árbol de navegación para el sidebar Angular/PrimeNG",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean")),
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
        $data = $this->service->index($request->boolean('paginate'));

        return $this->responseSuccess('Menús obtenidos correctamente', SiawMenusResource::collection($data));
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_menus/{siaw_menu}",
     *      tags={"Menus"},
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
     *      tags={"Menus"},
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
    public function store(StoreRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Menú creado correctamente', new SiawMenusResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_menus/{siaw_menu}",
     *      tags={"Menus"},
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
    public function update(UpdateRequest $request, SiawMenus $siaw_menu): JsonResponse
    {
        $model = $this->service->update($siaw_menu, $request->validated());

        return $this->responseSuccess('Menú actualizado correctamente', new SiawMenusResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_menus/{siaw_menu}",
     *      tags={"Menus"},
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
