<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siaw\SiawMenuPermiso\StoreRequest;
use App\Http\Requests\Siaw\SiawMenuPermiso\UpdateRequest;
use App\Http\Resources\Siaw\SiawMenuPermisoResource;
use App\Models\dbsiaw\SiawMenuPermiso;
use App\Services\Siaw\SiawMenuPermisoService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="MenuPermiso")
 */
class SiawMenuPermisoController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SiawMenuPermisoService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_menu_permiso",
     *      tags={"MenuPermiso"},
     *      summary="Qué permiso desbloquea cada menú",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean")),
     *      @OA\Response(
     *          response=200,
     *          description="Vínculos menú-permiso registrados",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Vínculos obtenidos correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawMenuPermisoSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->index($request->boolean('paginate'));

        return $this->responseSuccess('Vínculos obtenidos correctamente', SiawMenuPermisoResource::collection($data));
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_menu_permiso/{siaw_menu_permiso}",
     *      tags={"MenuPermiso"},
     *      summary="Ver un vínculo menú-permiso por UUID",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_menu_permiso", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Vínculo encontrado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Vínculo encontrado"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawMenuPermisoSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="No encontrado")
     * )
     */
    public function show(SiawMenuPermiso $siaw_menu_permiso): JsonResponse
    {
        $model = $this->service->show($siaw_menu_permiso);

        return $this->responseSuccess('Vínculo encontrado', new SiawMenuPermisoResource($model));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_menu_permiso",
     *      tags={"MenuPermiso"},
     *      summary="Vincular un permiso a un menú",
     *      description="Solo superuser/admin. Un menú admite un ÚNICO permiso requerido: si ya tiene uno, la alta se rechaza (422) — desvincular el actual antes de asignar otro.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"menu_id","permiso_id"},
     *              @OA\Property(property="menu_id", type="string", format="uuid"),
     *              @OA\Property(property="permiso_id", type="string", format="uuid")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Vínculo creado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Vínculo creado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawMenuPermisoSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida — el menú ya tiene un permiso, o la combinación menu_id+permiso_id ya existe")
     * )
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Vínculo creado correctamente', new SiawMenuPermisoResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_menu_permiso/{siaw_menu_permiso}",
     *      tags={"MenuPermiso"},
     *      summary="Actualizar un vínculo menú-permiso",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_menu_permiso", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="menu_id", type="string", format="uuid"),
     *              @OA\Property(property="permiso_id", type="string", format="uuid")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Vínculo actualizado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Vínculo actualizado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawMenuPermisoSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function update(UpdateRequest $request, SiawMenuPermiso $siaw_menu_permiso): JsonResponse
    {
        $model = $this->service->update($siaw_menu_permiso, $request->validated());

        return $this->responseSuccess('Vínculo actualizado correctamente', new SiawMenuPermisoResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_menu_permiso/{siaw_menu_permiso}",
     *      tags={"MenuPermiso"},
     *      summary="Quitar un vínculo menú-permiso",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_menu_permiso", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Vínculo eliminado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Vínculo eliminado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawMenuPermisoSchema")
     *          )
     *      )
     * )
     */
    public function destroy(SiawMenuPermiso $siaw_menu_permiso): JsonResponse
    {
        $deleted = $this->service->destroy($siaw_menu_permiso);

        return $this->responseSuccess('Vínculo eliminado correctamente', new SiawMenuPermisoResource($deleted));
    }
}
