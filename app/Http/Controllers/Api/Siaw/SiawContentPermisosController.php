<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siaw\SiawContentPermisos\BulkStoreRequest;
use App\Http\Requests\Siaw\SiawContentPermisos\StoreRequest;
use App\Http\Requests\Siaw\SiawContentPermisos\UpdateRequest;
use App\Http\Resources\Siaw\SiawContentPermisosResource;
use App\Models\dbsiaw\SiawContentPermisos;
use App\Services\Siaw\SiawContentPermisosService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="ContentPermisos")
 */
class SiawContentPermisosController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SiawContentPermisosService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_content_permisos",
     *      tags={"ContentPermisos"},
     *      summary="Listar el catálogo de permisos",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean")),
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
        $data = $this->service->index($request->boolean('paginate'));

        return $this->responseSuccess('Permisos obtenidos correctamente', SiawContentPermisosResource::collection($data));
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_content_permisos/{siaw_content_permiso}",
     *      tags={"ContentPermisos"},
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
     *      tags={"ContentPermisos"},
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
    public function store(StoreRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Permiso creado correctamente', new SiawContentPermisosResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_content_permisos/{siaw_content_permiso}",
     *      tags={"ContentPermisos"},
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
    public function update(UpdateRequest $request, SiawContentPermisos $siaw_content_permiso): JsonResponse
    {
        $model = $this->service->update($siaw_content_permiso, $request->validated());

        return $this->responseSuccess('Permiso actualizado correctamente', new SiawContentPermisosResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_content_permisos/{siaw_content_permiso}",
     *      tags={"ContentPermisos"},
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
     *      tags={"ContentPermisos"},
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


    public function bulkCreate(BulkStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $createdPermisos = $this->service->bulkCreate($data);

        return $this->responseCreated('Permisos creados correctamente', SiawContentPermisosResource::collection($createdPermisos));
    }
}
