<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\HandlesIndexResponse;
use App\Http\Requests\Siaw\ContentModel\StoreContentModelRequest;
use App\Http\Requests\Siaw\ContentModel\UpdateContentModelRequest;
use App\Http\Resources\Siaw\SiawContentModelResource;
use App\Http\Resources\Siaw\SiawContentModelTinyResource;
use App\Models\dbsiaw\SiawContentModel;
use App\Services\Siaw\SiawContentModelService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="siaw_content_model", description="Content model")
 */
class SiawContentModelController extends Controller
{
    use ApiResponse, HandlesIndexResponse;

    public function __construct(private readonly SiawContentModelService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_content_model",
     *      tags={"siaw_content_model"},
     *      summary="Listar el catálogo de modelos de contenido",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", description="true devuelve la respuesta paginada con `meta`", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="tiny", in="query", description="true devuelve SiawContentModelTinySchema", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="with_permisos", in="query", description="true incluye los permisos de cada modelo", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="sistema_id", in="query", description="Filtro exacto por sistema_id", @OA\Schema(type="string", format="uuid")),
     *      @OA\Parameter(name="app_label", in="query", description="Filtro exacto por app_label", @OA\Schema(type="string")),
     *      @OA\Parameter(name="app_model", in="query", description="Filtro exacto por app_model", @OA\Schema(type="string")),
     *      @OA\Parameter(name="search", in="query", description="Búsqueda de texto libre", @OA\Schema(type="string")),
     *      @OA\Parameter(name="sorts", in="query", description="Orden: campo o -campo (descendente)", @OA\Schema(type="string")),
     *      @OA\Parameter(name="include", in="query", description="Relaciones a cargar, separadas por coma (ej. permisos)", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response=200,
     *          description="Catálogo de modelos registrados",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Modelos obtenidos correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawContentModelSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $paginate = $request->boolean('paginate');

        $data = $this->service->index($paginate);

        $resource = $request->boolean('tiny')
            ? SiawContentModelTinyResource::class
            : SiawContentModelResource::class;

        return $this->responseIndex($data, $resource, $paginate, 'Modelos obtenidos correctamente');
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_content_model/{siaw_content_model}",
     *      tags={"siaw_content_model"},
     *      summary="Ver un modelo de contenido por UUID",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_content_model", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Modelo encontrado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Modelo encontrado"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawContentModelSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="No encontrado")
     * )
     */
    public function show(SiawContentModel $siaw_content_model): JsonResponse
    {
        $model = $this->service->show($siaw_content_model);

        return $this->responseSuccess('Modelo encontrado', new SiawContentModelResource($model));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_content_model",
     *      tags={"siaw_content_model"},
     *      summary="Registrar un modelo de contenido",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"app_label","app_model","nombre_display","sistema_id"},
     *              @OA\Property(property="app_label", type="string", maxLength=100),
     *              @OA\Property(property="app_model", type="string", maxLength=100, description="Único"),
     *              @OA\Property(property="nombre_display", type="string", maxLength=255),
     *              @OA\Property(property="sistema_id", type="string", format="uuid", description="Sistema al que pertenece el modelo; sus permisos lo heredan")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Modelo creado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Modelo creado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawContentModelSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(StoreContentModelRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Modelo creado correctamente', new SiawContentModelResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_content_model/{siaw_content_model}",
     *      tags={"siaw_content_model"},
     *      summary="Actualizar un modelo de contenido",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_content_model", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="app_label", type="string", maxLength=100),
     *              @OA\Property(property="app_model", type="string", maxLength=100, description="Único"),
     *              @OA\Property(property="nombre_display", type="string", maxLength=255),
     *              @OA\Property(property="sistema_id", type="string", format="uuid")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Modelo actualizado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Modelo actualizado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawContentModelSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function update(UpdateContentModelRequest $request, SiawContentModel $siaw_content_model): JsonResponse
    {
        $model = $this->service->update($siaw_content_model, $request->validated());

        return $this->responseSuccess('Modelo actualizado correctamente', new SiawContentModelResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_content_model/{siaw_content_model}",
     *      tags={"siaw_content_model"},
     *      summary="Eliminar un modelo de contenido",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_content_model", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Modelo eliminado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Modelo eliminado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawContentModelSchema")
     *          )
     *      )
     * )
     */
    public function destroy(SiawContentModel $siaw_content_model): JsonResponse
    {
        $deleted = $this->service->destroy($siaw_content_model);

        return $this->responseSuccess('Modelo eliminado correctamente', new SiawContentModelResource($deleted));
    }
}
