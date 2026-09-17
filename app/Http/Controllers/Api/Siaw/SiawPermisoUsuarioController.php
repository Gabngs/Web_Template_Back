<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siaw\SiawPermisoUsuario\StoreRequest;
use App\Http\Requests\Siaw\SiawPermisoUsuario\SyncRequest;
use App\Http\Requests\Siaw\SiawPermisoUsuario\UpdateRequest;
use App\Http\Resources\Siaw\SiawPermisoUsuarioResource;
use App\Models\dbsiaw\SiawPermisoUsuario;
use App\Services\Siaw\SiawPermisoUsuarioService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="PermisoUsuario")
 */
class SiawPermisoUsuarioController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SiawPermisoUsuarioService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_permiso_usuario",
     *      tags={"PermisoUsuario"},
     *      summary="Listar permisos asignados directamente a usuarios",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean")),
     *      @OA\Response(
     *          response=200,
     *          description="Asignaciones permiso-usuario registradas",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Asignaciones obtenidas correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawPermisoUsuarioSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->index($request->boolean('paginate'));

        return $this->responseSuccess('Asignaciones obtenidas correctamente', SiawPermisoUsuarioResource::collection($data));
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_permiso_usuario/{siaw_permiso_usuario}",
     *      tags={"PermisoUsuario"},
     *      summary="Ver una asignación permiso-usuario por UUID",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_permiso_usuario", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Asignación encontrada",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Asignación encontrada"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawPermisoUsuarioSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="No encontrada")
     * )
     */
    public function show(SiawPermisoUsuario $siaw_permiso_usuario): JsonResponse
    {
        $model = $this->service->show($siaw_permiso_usuario);

        return $this->responseSuccess('Asignación encontrada', new SiawPermisoUsuarioResource($model));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_permiso_usuario",
     *      tags={"PermisoUsuario"},
     *      summary="Asignar un permiso directamente a un usuario",
     *      description="Override puntual sobre lo que da el rol del usuario. Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"permiso_id","usuario_id"},
     *              @OA\Property(property="permiso_id", type="string", format="uuid"),
     *              @OA\Property(property="usuario_id", type="string", format="uuid"),
     *              @OA\Property(property="permitido", type="boolean", default=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Permiso asignado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Permiso asignado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawPermisoUsuarioSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Permiso asignado correctamente', new SiawPermisoUsuarioResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_permiso_usuario/{siaw_permiso_usuario}",
     *      tags={"PermisoUsuario"},
     *      summary="Actualizar una asignación permiso-usuario",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_permiso_usuario", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="permiso_id", type="string", format="uuid"),
     *              @OA\Property(property="usuario_id", type="string", format="uuid"),
     *              @OA\Property(property="permitido", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Asignación actualizada",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Asignación actualizada correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawPermisoUsuarioSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function update(UpdateRequest $request, SiawPermisoUsuario $siaw_permiso_usuario): JsonResponse
    {
        $model = $this->service->update($siaw_permiso_usuario, $request->validated());

        return $this->responseSuccess('Asignación actualizada correctamente', new SiawPermisoUsuarioResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_permiso_usuario/{siaw_permiso_usuario}",
     *      tags={"PermisoUsuario"},
     *      summary="Quitar un permiso asignado directamente a un usuario",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_permiso_usuario", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Permiso quitado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Permiso quitado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawPermisoUsuarioSchema")
     *          )
     *      )
     * )
     */
    public function destroy(SiawPermisoUsuario $siaw_permiso_usuario): JsonResponse
    {
        $deleted = $this->service->destroy($siaw_permiso_usuario);

        return $this->responseSuccess('Permiso quitado correctamente', new SiawPermisoUsuarioResource($deleted));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_permiso_usuario/sync",
     *      tags={"PermisoUsuario"},
     *      summary="Reemplazar los permisos concedidos directamente a un usuario (pick-list Disponibles/Asignados)",
     *      description="Manda el estado FINAL del pick-list, no un diff — agrega lo que falte y quita lo que sobre. Solo afecta filas con permitido=true; las denegaciones explícitas (permitido=false) no se tocan acá. permiso_ids puede ir vacío para quitar todos. Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"usuario_id","permiso_ids"},
     *              @OA\Property(property="usuario_id", type="string", format="uuid"),
     *              @OA\Property(property="permiso_ids", type="array", @OA\Items(type="string", format="uuid"))
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Permisos sincronizados",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Permisos sincronizados correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawPermisoUsuarioSchema"))
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function sync(SyncRequest $request): JsonResponse
    {
        $data = $this->service->sync($request->validated('usuario_id'), $request->validated('permiso_ids'));

        return $this->responseSuccess('Permisos sincronizados correctamente', SiawPermisoUsuarioResource::collection($data));
    }
}
