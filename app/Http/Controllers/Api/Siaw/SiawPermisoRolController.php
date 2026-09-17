<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siaw\SiawPermisoRol\StoreRequest;
use App\Http\Requests\Siaw\SiawPermisoRol\SyncRequest;
use App\Http\Requests\Siaw\SiawPermisoRol\UpdateRequest;
use App\Http\Resources\Siaw\SiawPermisoRolResource;
use App\Models\dbsiaw\SiawPermisoRol;
use App\Services\Siaw\SiawPermisoRolService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="PermisoRol")
 */
class SiawPermisoRolController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SiawPermisoRolService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_permiso_rol",
     *      tags={"PermisoRol"},
     *      summary="Listar permisos asignados a roles",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean")),
     *      @OA\Response(
     *          response=200,
     *          description="Asignaciones permiso-rol registradas",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Asignaciones obtenidas correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawPermisoRolSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->index($request->boolean('paginate'));

        return $this->responseSuccess('Asignaciones obtenidas correctamente', SiawPermisoRolResource::collection($data));
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_permiso_rol/{siaw_permiso_rol}",
     *      tags={"PermisoRol"},
     *      summary="Ver una asignación permiso-rol por UUID",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_permiso_rol", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Asignación encontrada",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Asignación encontrada"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawPermisoRolSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="No encontrada")
     * )
     */
    public function show(SiawPermisoRol $siaw_permiso_rol): JsonResponse
    {
        $model = $this->service->show($siaw_permiso_rol);

        return $this->responseSuccess('Asignación encontrada', new SiawPermisoRolResource($model));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_permiso_rol",
     *      tags={"PermisoRol"},
     *      summary="Asignar un permiso a un rol",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"permiso_id","rol_id"},
     *              @OA\Property(property="permiso_id", type="string", format="uuid"),
     *              @OA\Property(property="rol_id", type="string", format="uuid")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Permiso asignado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Permiso asignado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawPermisoRolSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Permiso asignado correctamente', new SiawPermisoRolResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_permiso_rol/{siaw_permiso_rol}",
     *      tags={"PermisoRol"},
     *      summary="Actualizar una asignación permiso-rol",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_permiso_rol", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="permiso_id", type="string", format="uuid"),
     *              @OA\Property(property="rol_id", type="string", format="uuid")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Asignación actualizada",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Asignación actualizada correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawPermisoRolSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function update(UpdateRequest $request, SiawPermisoRol $siaw_permiso_rol): JsonResponse
    {
        $model = $this->service->update($siaw_permiso_rol, $request->validated());

        return $this->responseSuccess('Asignación actualizada correctamente', new SiawPermisoRolResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_permiso_rol/{siaw_permiso_rol}",
     *      tags={"PermisoRol"},
     *      summary="Quitar un permiso de un rol",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_permiso_rol", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Permiso quitado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Permiso quitado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawPermisoRolSchema")
     *          )
     *      )
     * )
     */
    public function destroy(SiawPermisoRol $siaw_permiso_rol): JsonResponse
    {
        $deleted = $this->service->destroy($siaw_permiso_rol);

        return $this->responseSuccess('Permiso quitado correctamente', new SiawPermisoRolResource($deleted));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_permiso_rol/sync",
     *      tags={"PermisoRol"},
     *      summary="Reemplazar todos los permisos de un rol (pick-list Disponibles/Asignados)",
     *      description="Manda el estado FINAL del pick-list, no un diff — agrega lo que falte y quita lo que sobre. permiso_ids puede ir vacío para quitar todos. Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"rol_id","permiso_ids"},
     *              @OA\Property(property="rol_id", type="string", format="uuid"),
     *              @OA\Property(property="permiso_ids", type="array", @OA\Items(type="string", format="uuid"))
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Permisos sincronizados",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Permisos sincronizados correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawPermisoRolSchema"))
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function sync(SyncRequest $request): JsonResponse
    {
        $data = $this->service->sync($request->validated('rol_id'), $request->validated('permiso_ids'));

        return $this->responseSuccess('Permisos sincronizados correctamente', SiawPermisoRolResource::collection($data));
    }
}
