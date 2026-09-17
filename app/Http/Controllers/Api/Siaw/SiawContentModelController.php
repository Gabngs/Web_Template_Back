<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siaw\SiawContentModel\StoreRequest;
use App\Http\Requests\Siaw\SiawContentModel\UpdateRequest;
use App\Http\Resources\Siaw\SiawContentModelResource;
use App\Models\dbsiaw\SiawContentModel;
use App\Services\Siaw\SiawContentModelService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="ContentModel")
 */
class SiawContentModelController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SiawContentModelService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_content_model",
     *      tags={"ContentModel"},
     *      summary="Listar el catálogo de modelos de contenido",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean")),
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
        $data = $this->service->index($request->boolean('paginate'));

        return $this->responseSuccess('Modelos obtenidos correctamente', SiawContentModelResource::collection($data));
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_content_model/{siaw_content_model}",
     *      tags={"ContentModel"},
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
     *      tags={"ContentModel"},
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
    public function store(StoreRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Modelo creado correctamente', new SiawContentModelResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_content_model/{siaw_content_model}",
     *      tags={"ContentModel"},
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
    public function update(UpdateRequest $request, SiawContentModel $siaw_content_model): JsonResponse
    {
        $model = $this->service->update($siaw_content_model, $request->validated());

        return $this->responseSuccess('Modelo actualizado correctamente', new SiawContentModelResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_content_model/{siaw_content_model}",
     *      tags={"ContentModel"},
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
