<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\HandlesIndexResponse;
use App\Http\Requests\Siaw\Roles\StoreRolesRequest;
use App\Http\Requests\Siaw\Roles\UpdateRolesRequest;
use App\Http\Resources\Siaw\SiawRolesResource;
use App\Http\Resources\Siaw\SiawRolesTinyResource;
use App\Models\dbsiaw\SiawRoles;
use App\Services\Siaw\SiawRolesService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="siaw_roles", description="Roles")
 */
class SiawRolesController extends Controller
{
    use ApiResponse, HandlesIndexResponse;

    public function __construct(private readonly SiawRolesService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_roles",
     *      tags={"siaw_roles"},
     *      summary="Listar el catálogo de roles",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", description="true devuelve la respuesta paginada con `meta`", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="tiny", in="query", description="true devuelve SiawRolesTinySchema", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="slug", in="query", description="Filtro exacto por slug", @OA\Schema(type="string")),
     *      @OA\Parameter(name="activo", in="query", description="Filtro exacto por activo", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="search", in="query", description="Búsqueda de texto libre", @OA\Schema(type="string")),
     *      @OA\Parameter(name="sorts", in="query", description="Orden: campo o -campo (descendente)", @OA\Schema(type="string")),
     *      @OA\Parameter(name="include", in="query", description="Relaciones a cargar, separadas por coma", @OA\Schema(type="string")),
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
        $paginate = $request->boolean('paginate');

        $data = $this->service->index($paginate);

        $resource = $request->boolean('tiny')
            ? SiawRolesTinyResource::class
            : SiawRolesResource::class;

        return $this->responseIndex($data, $resource, $paginate, 'Roles obtenidos correctamente');
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_roles/{siaw_role}",
     *      tags={"siaw_roles"},
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
     *      tags={"siaw_roles"},
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
    public function store(StoreRolesRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Rol creado correctamente', new SiawRolesResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_roles/{siaw_role}",
     *      tags={"siaw_roles"},
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
    public function update(UpdateRolesRequest $request, SiawRoles $siaw_role): JsonResponse
    {
        $model = $this->service->update($siaw_role, $request->validated());

        return $this->responseSuccess('Rol actualizado correctamente', new SiawRolesResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_roles/{siaw_role}",
     *      tags={"siaw_roles"},
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
