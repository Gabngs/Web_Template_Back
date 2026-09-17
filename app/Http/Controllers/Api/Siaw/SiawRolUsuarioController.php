<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siaw\SiawRolUsuario\StoreRequest;
use App\Http\Requests\Siaw\SiawRolUsuario\SyncRequest;
use App\Http\Requests\Siaw\SiawRolUsuario\UpdateRequest;
use App\Http\Resources\Siaw\SiawRolUsuarioResource;
use App\Models\dbsiaw\SiawRolUsuario;
use App\Services\Siaw\SiawRolUsuarioService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="RolUsuario")
 */
class SiawRolUsuarioController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SiawRolUsuarioService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_rol_usuario",
     *      tags={"RolUsuario"},
     *      summary="Listar asignaciones rol-usuario",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean")),
     *      @OA\Response(
     *          response=200,
     *          description="Asignaciones rol-usuario registradas",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Asignaciones obtenidas correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawRolUsuarioSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->index($request->boolean('paginate'));

        return $this->responseSuccess('Asignaciones obtenidas correctamente', SiawRolUsuarioResource::collection($data));
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_rol_usuario/{siaw_rol_usuario}",
     *      tags={"RolUsuario"},
     *      summary="Ver una asignación rol-usuario por UUID",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_rol_usuario", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Asignación encontrada",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Asignación encontrada"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawRolUsuarioSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="No encontrada")
     * )
     */
    public function show(SiawRolUsuario $siaw_rol_usuario): JsonResponse
    {
        $model = $this->service->show($siaw_rol_usuario);

        return $this->responseSuccess('Asignación encontrada', new SiawRolUsuarioResource($model));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_rol_usuario",
     *      tags={"RolUsuario"},
     *      summary="Asignar un rol a un usuario",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"usuario_id","rol_id"},
     *              @OA\Property(property="usuario_id", type="string", format="uuid"),
     *              @OA\Property(property="rol_id", type="string", format="uuid")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Rol asignado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Rol asignado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawRolUsuarioSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Rol asignado correctamente', new SiawRolUsuarioResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_rol_usuario/{siaw_rol_usuario}",
     *      tags={"RolUsuario"},
     *      summary="Actualizar una asignación rol-usuario",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_rol_usuario", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="usuario_id", type="string", format="uuid"),
     *              @OA\Property(property="rol_id", type="string", format="uuid")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Asignación actualizada",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Asignación actualizada correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawRolUsuarioSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function update(UpdateRequest $request, SiawRolUsuario $siaw_rol_usuario): JsonResponse
    {
        $model = $this->service->update($siaw_rol_usuario, $request->validated());

        return $this->responseSuccess('Asignación actualizada correctamente', new SiawRolUsuarioResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_rol_usuario/{siaw_rol_usuario}",
     *      tags={"RolUsuario"},
     *      summary="Quitar un rol de un usuario",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_rol_usuario", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Rol quitado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Rol quitado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawRolUsuarioSchema")
     *          )
     *      )
     * )
     */
    public function destroy(SiawRolUsuario $siaw_rol_usuario): JsonResponse
    {
        $deleted = $this->service->destroy($siaw_rol_usuario);

        return $this->responseSuccess('Rol quitado correctamente', new SiawRolUsuarioResource($deleted));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_rol_usuario/sync",
     *      tags={"RolUsuario"},
     *      summary="Reemplazar todos los roles de un usuario (pick-list Disponibles/Asignados)",
     *      description="Manda el estado FINAL del pick-list, no un diff — agrega lo que falte y quita lo que sobre. rol_ids puede ir vacío para quitar todos. No cambia cómo se resuelven los permisos hoy (siaw_usuarios.rol_id sigue siendo la fuente). Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"usuario_id","rol_ids"},
     *              @OA\Property(property="usuario_id", type="string", format="uuid"),
     *              @OA\Property(property="rol_ids", type="array", @OA\Items(type="string", format="uuid"))
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Roles sincronizados",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Roles sincronizados correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawRolUsuarioSchema"))
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function sync(SyncRequest $request): JsonResponse
    {
        $data = $this->service->sync($request->validated('usuario_id'), $request->validated('rol_ids'));

        return $this->responseSuccess('Roles sincronizados correctamente', SiawRolUsuarioResource::collection($data));
    }
}
