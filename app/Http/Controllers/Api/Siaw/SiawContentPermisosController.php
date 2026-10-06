<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\HandlesIndexResponse;
use App\Http\Requests\Siaw\ContentPermisos\BulkStoreContentPermisosRequest;
use App\Http\Requests\Siaw\ContentPermisos\StoreContentPermisosRequest;
use App\Http\Requests\Siaw\ContentPermisos\UpdateContentPermisosRequest;
use App\Http\Resources\Siaw\SiawContentPermisosResource;
use App\Http\Resources\Siaw\SiawContentPermisosTinyResource;
use App\Models\dbsiaw\SiawContentPermisos;
use App\Services\Siaw\SiawContentPermisosService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="siaw_content_permisos", description="Content permisos")
 */
class SiawContentPermisosController extends Controller
{
    use ApiResponse, HandlesIndexResponse;

    public function __construct(private readonly SiawContentPermisosService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_content_permisos",
     *      tags={"siaw_content_permisos"},
     *      summary="Listar el catálogo de permisos",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", description="true devuelve la respuesta paginada con `meta`", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="tiny", in="query", description="true devuelve SiawContentPermisosTinySchema", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="content_model_id", in="query", description="Filtro exacto por content_model_id", @OA\Schema(type="string", format="uuid")),
     *      @OA\Parameter(name="sistema_id", in="query", description="Filtro exacto por sistema_id", @OA\Schema(type="string", format="uuid")),
     *      @OA\Parameter(name="app_model", in="query", description="Filtra por app_model del modelo de contenido", @OA\Schema(type="string")),
     *      @OA\Parameter(name="codename", in="query", description="Filtro exacto por codename", @OA\Schema(type="string")),
     *      @OA\Parameter(name="search", in="query", description="Búsqueda de texto libre", @OA\Schema(type="string")),
     *      @OA\Parameter(name="sorts", in="query", description="Orden: campo o -campo (descendente)", @OA\Schema(type="string")),
     *      @OA\Parameter(name="include", in="query", description="Relaciones a cargar, separadas por coma", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response=200,
     *          description="Catálogo de permisos registrados",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Permisos obtenidos correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawContentPermisosSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $paginate = $request->boolean('paginate');

        $data = $this->service->index($paginate);

        $resource = $request->boolean('tiny')
            ? SiawContentPermisosTinyResource::class
            : SiawContentPermisosResource::class;

        return $this->responseIndex($data, $resource, $paginate, 'Permisos obtenidos correctamente');
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_content_permisos/{siaw_content_permiso}",
     *      tags={"siaw_content_permisos"},
     *      summary="Ver un permiso por UUID",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_content_permiso", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Permiso encontrado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Permiso encontrado"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawContentPermisosSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="No encontrado")
     * )
     */
    public function show(SiawContentPermisos $siaw_content_permiso): JsonResponse
    {
        $model = $this->service->show($siaw_content_permiso);

        return $this->responseSuccess('Permiso encontrado', new SiawContentPermisosResource($model));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_content_permisos",
     *      tags={"siaw_content_permisos"},
     *      summary="Registrar un permiso",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"content_model_id","codename","desc"},
     *              @OA\Property(property="content_model_id", type="string", format="uuid"),
     *              @OA\Property(property="codename", type="string", maxLength=100, description="Único"),
     *              @OA\Property(property="desc", type="string", maxLength=255)
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Permiso creado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Permiso creado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawContentPermisosSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(StoreContentPermisosRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Permiso creado correctamente', new SiawContentPermisosResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_content_permisos/{siaw_content_permiso}",
     *      tags={"siaw_content_permisos"},
     *      summary="Actualizar un permiso",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_content_permiso", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="content_model_id", type="string", format="uuid"),
     *              @OA\Property(property="codename", type="string", maxLength=100, description="Único"),
     *              @OA\Property(property="desc", type="string", maxLength=255)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Permiso actualizado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Permiso actualizado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawContentPermisosSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function update(UpdateContentPermisosRequest $request, SiawContentPermisos $siaw_content_permiso): JsonResponse
    {
        $model = $this->service->update($siaw_content_permiso, $request->validated());

        return $this->responseSuccess('Permiso actualizado correctamente', new SiawContentPermisosResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_content_permisos/{siaw_content_permiso}",
     *      tags={"siaw_content_permisos"},
     *      summary="Eliminar un permiso",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_content_permiso", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Permiso eliminado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Permiso eliminado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawContentPermisosSchema")
     *          )
     *      )
     * )
     */
    public function destroy(SiawContentPermisos $siaw_content_permiso): JsonResponse
    {
        $deleted = $this->service->destroy($siaw_content_permiso);

        return $this->responseSuccess('Permiso eliminado correctamente', new SiawContentPermisosResource($deleted));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_content_permisos/bulk",
     *      tags={"siaw_content_permisos"},
     *      summary="Registrar múltiples permisos para un modelo",
     *      description="Solo superuser/admin. Crea múltiples permisos asociados al mismo modelo de contenido.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"content_model_id","permisos"},
     *              @OA\Property(property="content_model_id", type="string", format="uuid", description="ID del modelo de contenido"),
     *              @OA\Property(property="permisos", type="array", minItems=1, description="Array de permisos (mínimo 1)", @OA\Items(
     *                  required={"codename","desc"},
     *                  @OA\Property(property="codename", type="string", maxLength=100, description="Código único del permiso"),
     *                  @OA\Property(property="desc", type="string", maxLength=255, description="Descripción del permiso")
     *              ))
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Permisos creados exitosamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Permisos creados correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawContentPermisosSchema"))
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */


    public function bulkCreate(BulkStoreContentPermisosRequest $request): JsonResponse
    {
        $data = $request->validated();
        $createdPermisos = $this->service->bulkCreate($data);

        return $this->responseCreated('Permisos creados correctamente', SiawContentPermisosResource::collection($createdPermisos));
    }
}
