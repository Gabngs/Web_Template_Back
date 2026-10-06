<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\HandlesIndexResponse;
use App\Http\Requests\Siaw\Parametros\StoreParametrosRequest;
use App\Http\Requests\Siaw\Parametros\UpdateParametrosRequest;
use App\Http\Resources\Siaw\SiawParametrosResource;
use App\Http\Resources\Siaw\SiawParametrosTinyResource;
use App\Models\dbsiaw\SiawParametros;
use App\Services\Siaw\SiawParametrosService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="siaw_parametros", description="Parametros")
 */
class SiawParametrosController extends Controller
{
    use ApiResponse, HandlesIndexResponse;

    public function __construct(protected SiawParametrosService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_parametros",
     *      tags={"siaw_parametros"},
     *      summary="Listar parametros",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", description="true devuelve la respuesta paginada con `meta`", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="tiny", in="query", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="sistema_id", in="query", description="Filtro exacto por sistema_id", @OA\Schema(type="string", format="uuid")),
     *      @OA\Parameter(name="codigo", in="query", description="Filtro exacto por codigo", @OA\Schema(type="string")),
     *      @OA\Parameter(name="descripcion", in="query", description="Filtro exacto por descripcion", @OA\Schema(type="string")),
     *      @OA\Parameter(name="valor", in="query", description="Filtro exacto por valor", @OA\Schema(type="string")),
     *      @OA\Parameter(name="tipodato_id", in="query", description="Filtro exacto por tipodato_id", @OA\Schema(type="string", format="uuid")),
     *      @OA\Parameter(name="activo", in="query", description="Filtro exacto por activo", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="search", in="query", description="Búsqueda de texto libre", @OA\Schema(type="string")),
     *      @OA\Parameter(name="sorts", in="query", description="Orden: campo o -campo (descendente)", @OA\Schema(type="string")),
     *      @OA\Parameter(name="include", in="query", description="Relaciones a cargar, separadas por coma", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response=200,
     *          description="Listado de parametros. Con ?tiny=true, cada item de `data` sigue SiawParametrosTinySchema en vez de SiawParametrosSchema. Con ?paginate=true, se agrega `meta` al mismo nivel que `data` — ver ApiResponse.md#Paginación sin duplicar `data`.",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registros obtenidos correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawParametrosSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request)
    {
        $paginate = $request->boolean('paginate');

        $data = $this->service->index($paginate);

        $resource = $request->boolean('tiny')
            ? SiawParametrosTinyResource::class
            : SiawParametrosResource::class;

        return $this->responseIndex($data, $resource, $paginate);
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_parametros/{id}",
     *      tags={"siaw_parametros"},
     *      summary="Obtener registro de parametros por id",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Registro obtenido correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro obtenido correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawParametrosSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado")
     * )
     */
    public function show(SiawParametros $siaw_parametros)
    {
        $model = $this->service->show($siaw_parametros);

        return $this->responseSuccess(
            'Registro obtenido correctamente',
            new SiawParametrosResource($model)
        );
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_parametros",
     *      tags={"siaw_parametros"},
     *      summary="Crear registro de parametros",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={ "codigo", "descripcion", "tipodato_id" },
     *              @OA\Property(property="sistema_id", type="string", format="uuid"),
     *              @OA\Property(property="codigo", type="string", maxLength=80),
     *              @OA\Property(property="descripcion", type="string", maxLength=255),
     *              @OA\Property(property="valor", type="string", maxLength=255),
     *              @OA\Property(property="tipodato_id", type="string", format="uuid"),
     *              @OA\Property(property="activo", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Registro creado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Registro creado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawParametrosSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function store(StoreParametrosRequest $request)
    {
        $model = $this->service->store($request->validated());

        return $this->responseSuccess(
            'Registro creado correctamente',
            new SiawParametrosResource($model)
        );
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_parametros/{id}",
     *      tags={"siaw_parametros"},
     *      summary="Actualizar registro de parametros",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="sistema_id", type="string", format="uuid"),
     *              @OA\Property(property="codigo", type="string", maxLength=80),
     *              @OA\Property(property="descripcion", type="string", maxLength=255),
     *              @OA\Property(property="valor", type="string", maxLength=255),
     *              @OA\Property(property="tipodato_id", type="string", format="uuid"),
     *              @OA\Property(property="activo", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Registro actualizado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro actualizado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawParametrosSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado"),
     *      @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function update(UpdateParametrosRequest $request, SiawParametros $siaw_parametros)
    {
        $model = $this->service->update($siaw_parametros, $request->validated());

        return $this->responseSuccess(
            'Registro actualizado correctamente',
            new SiawParametrosResource($model)
        );
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_parametros/{id}",
     *      tags={"siaw_parametros"},
     *      summary="Eliminar registro de parametros",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Registro eliminado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro eliminado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawParametrosSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado")
     * )
     */
    public function destroy(SiawParametros $siaw_parametros)
    {
        $deleted = $this->service->destroy($siaw_parametros);

        return $this->responseSuccess(
            'Registro eliminado correctamente',
            new SiawParametrosResource($deleted)
        );
    }
}
