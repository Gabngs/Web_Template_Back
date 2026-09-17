<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siaw\SiawRoles\StoreRequest;
use App\Http\Requests\Siaw\SiawRoles\UpdateRequest;
use App\Http\Resources\Siaw\SiawRolesResource;
use App\Models\dbsiaw\SiawRoles;
use App\Services\Siaw\SiawRolesService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Roles")
 */
class SiawRolesController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SiawRolesService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_roles",
     *      tags={"Roles"},
     *      summary="Listar el catálogo de roles",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean")),
     *      @OA\Response(
     *          response=200,
     *          description="Roles registrados",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Roles obtenidos correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawRolesSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->index($request->boolean('paginate'));

        return $this->responseSuccess('Roles obtenidos correctamente', SiawRolesResource::collection($data));
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_roles/{siaw_role}",
     *      tags={"Roles"},
     *      summary="Ver un rol por UUID",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_role", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Rol encontrado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Rol encontrado"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawRolesSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="No encontrado")
     * )
     */
    public function show(SiawRoles $siaw_role): JsonResponse
    {
        $model = $this->service->show($siaw_role);

        return $this->responseSuccess('Rol encontrado', new SiawRolesResource($model));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_roles",
     *      tags={"Roles"},
     *      summary="Registrar un rol",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"name","slug"},
     *              @OA\Property(property="name", type="string", maxLength=100, description="Único"),
     *              @OA\Property(property="slug", type="string", maxLength=100, description="Único"),
     *              @OA\Property(property="guard_name", type="string", maxLength=50),
     *              @OA\Property(property="descripcion", type="string", maxLength=255),
     *              @OA\Property(property="activo", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Rol creado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Rol creado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawRolesSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Rol creado correctamente', new SiawRolesResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_roles/{siaw_role}",
     *      tags={"Roles"},
     *      summary="Actualizar un rol",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_role", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="name", type="string", maxLength=100, description="Único"),
     *              @OA\Property(property="slug", type="string", maxLength=100, description="Único"),
     *              @OA\Property(property="guard_name", type="string", maxLength=50),
     *              @OA\Property(property="descripcion", type="string", maxLength=255),
     *              @OA\Property(property="activo", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Rol actualizado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Rol actualizado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawRolesSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function update(UpdateRequest $request, SiawRoles $siaw_role): JsonResponse
    {
        $model = $this->service->update($siaw_role, $request->validated());

        return $this->responseSuccess('Rol actualizado correctamente', new SiawRolesResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_roles/{siaw_role}",
     *      tags={"Roles"},
     *      summary="Eliminar un rol",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_role", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Rol eliminado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Rol eliminado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawRolesSchema")
     *          )
     *      )
     * )
     */
    public function destroy(SiawRoles $siaw_role): JsonResponse
    {
        $deleted = $this->service->destroy($siaw_role);

        return $this->responseSuccess('Rol eliminado correctamente', new SiawRolesResource($deleted));
    }
}
