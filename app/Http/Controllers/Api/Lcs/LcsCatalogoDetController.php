<?php

namespace App\Http\Controllers\Api\Lcs;

use App\Http\Controllers\Controller;
use App\Http\Traits\HandlesIndexResponse;
use App\Http\Requests\Lcs\CatalogoDet\StoreCatalogoDetRequest;
use App\Http\Requests\Lcs\CatalogoDet\UpdateCatalogoDetRequest;
use App\Http\Resources\Lcs\LcsCatalogoDetResource;
use App\Http\Resources\Lcs\LcsCatalogoDetTinyResource;
use App\Models\dblcs\LcsCatalogoDet;
use App\Services\Lcs\LcsCatalogoDetService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="lcs_catalogo_det", description="Catalogo det")
 */
class LcsCatalogoDetController extends Controller
{
    use ApiResponse, HandlesIndexResponse;

    public function __construct(protected LcsCatalogoDetService $service) {}

    /**
     * @OA\Get(
     *      path="/api/lcs_catalogo_det",
     *      tags={"lcs_catalogo_det"},
     *      summary="Listar catalogo det",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", description="true devuelve la respuesta paginada con `meta`", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="tiny", in="query", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="catalogo_id", in="query", description="Filtro exacto por catalogo_id", @OA\Schema(type="string", format="uuid")),
     *      @OA\Parameter(name="codigo", in="query", description="Filtro exacto por codigo", @OA\Schema(type="string")),
     *      @OA\Parameter(name="abreviatura", in="query", description="Filtro exacto por abreviatura", @OA\Schema(type="string")),
     *      @OA\Parameter(name="nombre", in="query", description="Filtro exacto por nombre", @OA\Schema(type="string")),
     *      @OA\Parameter(name="descripcion", in="query", description="Filtro exacto por descripcion", @OA\Schema(type="string")),
     *      @OA\Parameter(name="valor_numerico", in="query", description="Filtro exacto por valor_numerico", @OA\Schema(type="number", format="float")),
     *      @OA\Parameter(name="valor_texto", in="query", description="Filtro exacto por valor_texto", @OA\Schema(type="string")),
     *      @OA\Parameter(name="activo", in="query", description="Filtro exacto por activo", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="search", in="query", description="Búsqueda de texto libre", @OA\Schema(type="string")),
     *      @OA\Parameter(name="sorts", in="query", description="Orden: campo o -campo (descendente)", @OA\Schema(type="string")),
     *      @OA\Parameter(name="include", in="query", description="Relaciones a cargar, separadas por coma", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response=200,
     *          description="Listado de catalogo det. Con ?tiny=true, cada item de `data` sigue LcsCatalogoDetTinySchema en vez de LcsCatalogoDetSchema. Con ?paginate=true, se agrega `meta` al mismo nivel que `data` — ver ApiResponse.md#Paginación sin duplicar `data`.",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registros obtenidos correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/LcsCatalogoDetSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request)
    {
        $paginate = $request->boolean('paginate');

        $data = $this->service->index($paginate);

        $resource = $request->boolean('tiny')
            ? LcsCatalogoDetTinyResource::class
            : LcsCatalogoDetResource::class;

        return $this->responseIndex($data, $resource, $paginate);
    }

    /**
     * @OA\Get(
     *      path="/api/lcs_catalogo_det/{id}",
     *      tags={"lcs_catalogo_det"},
     *      summary="Obtener registro de catalogo det por id",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Registro obtenido correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro obtenido correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/LcsCatalogoDetSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado")
     * )
     */
    public function show(LcsCatalogoDet $lcs_catalogo_det)
    {
        $model = $this->service->show($lcs_catalogo_det);

        return $this->responseSuccess(
            'Registro obtenido correctamente',
            new LcsCatalogoDetResource($model)
        );
    }

    /**
     * @OA\Post(
     *      path="/api/lcs_catalogo_det",
     *      tags={"lcs_catalogo_det"},
     *      summary="Crear registro de catalogo det",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={ "catalogo_id", "codigo", "nombre" },
     *              @OA\Property(property="catalogo_id", type="string", format="uuid"),
     *              @OA\Property(property="codigo", type="string", maxLength=60),
     *              @OA\Property(property="abreviatura", type="string", maxLength=20),
     *              @OA\Property(property="nombre", type="string", maxLength=150),
     *              @OA\Property(property="descripcion", type="string", maxLength=255),
     *              @OA\Property(property="valor_numerico", type="number", format="float"),
     *              @OA\Property(property="valor_texto", type="string", maxLength=255),
     *              @OA\Property(property="activo", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Registro creado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Registro creado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/LcsCatalogoDetSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function store(StoreCatalogoDetRequest $request)
    {
        $model = $this->service->store($request->validated());

        return $this->responseSuccess(
            'Registro creado correctamente',
            new LcsCatalogoDetResource($model)
        );
    }

    /**
     * @OA\Put(
     *      path="/api/lcs_catalogo_det/{id}",
     *      tags={"lcs_catalogo_det"},
     *      summary="Actualizar registro de catalogo det",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="catalogo_id", type="string", format="uuid"),
     *              @OA\Property(property="codigo", type="string", maxLength=60),
     *              @OA\Property(property="abreviatura", type="string", maxLength=20),
     *              @OA\Property(property="nombre", type="string", maxLength=150),
     *              @OA\Property(property="descripcion", type="string", maxLength=255),
     *              @OA\Property(property="valor_numerico", type="number", format="float"),
     *              @OA\Property(property="valor_texto", type="string", maxLength=255),
     *              @OA\Property(property="activo", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Registro actualizado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro actualizado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/LcsCatalogoDetSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado"),
     *      @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function update(UpdateCatalogoDetRequest $request, LcsCatalogoDet $lcs_catalogo_det)
    {
        $model = $this->service->update($lcs_catalogo_det, $request->validated());

        return $this->responseSuccess(
            'Registro actualizado correctamente',
            new LcsCatalogoDetResource($model)
        );
    }

    /**
     * @OA\Delete(
     *      path="/api/lcs_catalogo_det/{id}",
     *      tags={"lcs_catalogo_det"},
     *      summary="Eliminar registro de catalogo det",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Registro eliminado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro eliminado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/LcsCatalogoDetSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado")
     * )
     */
    public function destroy(LcsCatalogoDet $lcs_catalogo_det)
    {
        $deleted = $this->service->destroy($lcs_catalogo_det);

        return $this->responseSuccess(
            'Registro eliminado correctamente',
            new LcsCatalogoDetResource($deleted)
        );
    }
}
