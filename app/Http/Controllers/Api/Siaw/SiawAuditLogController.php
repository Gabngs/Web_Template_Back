<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\HandlesIndexResponse;
use App\Http\Requests\Siaw\AuditLog\StoreAuditLogRequest;
use App\Http\Requests\Siaw\AuditLog\UpdateAuditLogRequest;
use App\Http\Resources\Siaw\SiawAuditLogResource;
use App\Models\dbsiaw\SiawAuditLog;
use App\Services\Siaw\SiawAuditLogService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="siaw_audit_log", description="Audit log")
 */
class SiawAuditLogController extends Controller
{
    use ApiResponse, HandlesIndexResponse;

    public function __construct(protected SiawAuditLogService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_audit_log",
     *      tags={"siaw_audit_log"},
     *      summary="Listar audit log",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", description="true devuelve la respuesta paginada con `meta`", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="nombre_proceso", in="query", description="Filtro exacto por nombre_proceso", @OA\Schema(type="string")),
     *      @OA\Parameter(name="tipo_proceso", in="query", description="1=inserción 2=actualización 3=eliminación 4=restauración", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="estado_proceso", in="query", description="1=éxito 2=error", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="origen", in="query", description="1=manual 2=automático", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="modelo_afectado", in="query", description="Filtro exacto por modelo_afectado", @OA\Schema(type="string")),
     *      @OA\Parameter(name="registro_id", in="query", description="Filtro exacto por registro_id", @OA\Schema(type="string")),
     *      @OA\Parameter(name="search", in="query", description="Búsqueda de texto libre", @OA\Schema(type="string")),
     *      @OA\Parameter(name="sorts", in="query", description="Orden: campo o -campo (descendente)", @OA\Schema(type="string")),
     *      @OA\Parameter(name="include", in="query", description="Relaciones a cargar, separadas por coma", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response=200,
     *          description="Listado de audit log. Con ?paginate=true, se agrega `meta` al mismo nivel que `data` — ver ApiResponse.md#Paginación sin duplicar `data`.",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registros obtenidos correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawAuditLogSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request)
    {
        $paginate = $request->boolean('paginate');

        $data = $this->service->index($paginate);

        return $this->responseIndex($data, SiawAuditLogResource::class, $paginate);
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_audit_log/{id}",
     *      tags={"siaw_audit_log"},
     *      summary="Obtener registro de audit log por id",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Registro obtenido correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro obtenido correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawAuditLogSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado")
     * )
     */
    public function show(SiawAuditLog $siaw_audit_log)
    {
        $model = $this->service->show($siaw_audit_log);

        return $this->responseSuccess(
            'Registro obtenido correctamente',
            new SiawAuditLogResource($model)
        );
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_audit_log",
     *      tags={"siaw_audit_log"},
     *      summary="Crear registro de audit log",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={ "nombre_proceso", "tipo_proceso", "estado_proceso", "origen" },
     *              @OA\Property(property="nombre_proceso", type="string", maxLength=255),
     *              @OA\Property(property="tipo_proceso", type="integer"),
     *              @OA\Property(property="estado_proceso", type="integer"),
     *              @OA\Property(property="origen", type="integer"),
     *              @OA\Property(property="modelo_afectado", type="string", maxLength=255),
     *              @OA\Property(property="registro_id", type="string", maxLength=255),
     *              @OA\Property(property="input", type="object"),
     *              @OA\Property(property="output", type="object"),
     *              @OA\Property(property="error", type="string")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Registro creado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Registro creado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawAuditLogSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function store(StoreAuditLogRequest $request)
    {
        $model = $this->service->store($request->validated());

        return $this->responseSuccess(
            'Registro creado correctamente',
            new SiawAuditLogResource($model)
        );
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_audit_log/{id}",
     *      tags={"siaw_audit_log"},
     *      summary="Actualizar registro de audit log",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="nombre_proceso", type="string", maxLength=255),
     *              @OA\Property(property="tipo_proceso", type="integer"),
     *              @OA\Property(property="estado_proceso", type="integer"),
     *              @OA\Property(property="origen", type="integer"),
     *              @OA\Property(property="modelo_afectado", type="string", maxLength=255),
     *              @OA\Property(property="registro_id", type="string", maxLength=255),
     *              @OA\Property(property="input", type="object"),
     *              @OA\Property(property="output", type="object"),
     *              @OA\Property(property="error", type="string")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Registro actualizado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro actualizado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawAuditLogSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado"),
     *      @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function update(UpdateAuditLogRequest $request, SiawAuditLog $siaw_audit_log)
    {
        $model = $this->service->update($siaw_audit_log, $request->validated());

        return $this->responseSuccess(
            'Registro actualizado correctamente',
            new SiawAuditLogResource($model)
        );
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_audit_log/{id}",
     *      tags={"siaw_audit_log"},
     *      summary="Eliminar registro de audit log",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Registro eliminado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro eliminado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawAuditLogSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado")
     * )
     */
    public function destroy(SiawAuditLog $siaw_audit_log)
    {
        $deleted = $this->service->destroy($siaw_audit_log);

        return $this->responseSuccess(
            'Registro eliminado correctamente',
            new SiawAuditLogResource($deleted)
        );
    }
}
