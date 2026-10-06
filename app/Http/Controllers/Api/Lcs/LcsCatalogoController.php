<?php

namespace App\Http\Controllers\Api\Lcs;

use App\Http\Controllers\Controller;
use App\Http\Traits\HandlesIndexResponse;
use App\Http\Requests\Lcs\Catalogo\StoreCatalogoRequest;
use App\Http\Requests\Lcs\Catalogo\UpdateCatalogoRequest;
use App\Http\Resources\Lcs\LcsCatalogoResource;
use App\Http\Resources\Lcs\LcsCatalogoTinyResource;
use App\Models\dblcs\LcsCatalogo;
use App\Services\Lcs\LcsCatalogoService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="lcs_catalogo", description="Catalogo")
 */
class LcsCatalogoController extends Controller
{
    use ApiResponse, HandlesIndexResponse;

    public function __construct(protected LcsCatalogoService $service) {}

    /**
     * @OA\Get(
     *      path="/api/lcs_catalogo",
     *      tags={"lcs_catalogo"},
     *      summary="Listar catalogo",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", description="true devuelve la respuesta paginada con `meta`", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *      @OA\Parameter(name="tiny", in="query", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="codigo", in="query", description="Filtro exacto por codigo", @OA\Schema(type="string")),
     *      @OA\Parameter(name="nombre", in="query", description="Filtro exacto por nombre", @OA\Schema(type="string")),
     *      @OA\Parameter(name="descripcion", in="query", description="Filtro exacto por descripcion", @OA\Schema(type="string")),
     *      @OA\Parameter(name="activo", in="query", description="Filtro exacto por activo", @OA\Schema(type="boolean")),
     *      @OA\Parameter(name="search", in="query", description="Búsqueda de texto libre", @OA\Schema(type="string")),
     *      @OA\Parameter(name="sorts", in="query", description="Orden: campo o -campo (descendente)", @OA\Schema(type="string")),
     *      @OA\Parameter(name="include", in="query", description="Relaciones a cargar, separadas por coma", @OA\Schema(type="string")),
     *      @OA\Response(
     *          response=200,
     *          description="Listado de catalogo. Con ?tiny=true, cada item de `data` sigue LcsCatalogoTinySchema en vez de LcsCatalogoSchema. Con ?paginate=true, se agrega `meta` al mismo nivel que `data` — ver ApiResponse.md#Paginación sin duplicar `data`.",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registros obtenidos correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/LcsCatalogoSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request)
    {
        $paginate = $request->boolean('paginate');

        $data = $this->service->index($paginate);

        $resource = $request->boolean('tiny')
            ? LcsCatalogoTinyResource::class
            : LcsCatalogoResource::class;

        return $this->responseIndex($data, $resource, $paginate);
    }

    /**
     * @OA\Get(
     *      path="/api/lcs_catalogo/{id}",
     *      tags={"lcs_catalogo"},
     *      summary="Obtener registro de catalogo por id",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Registro obtenido correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro obtenido correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/LcsCatalogoSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado")
     * )
     */
    public function show(LcsCatalogo $lcs_catalogo)
    {
        $model = $this->service->show($lcs_catalogo);

        return $this->responseSuccess(
            'Registro obtenido correctamente',
            new LcsCatalogoResource($model)
        );
    }

    /**
     * @OA\Post(
     *      path="/api/lcs_catalogo",
     *      tags={"lcs_catalogo"},
     *      summary="Crear registro de catalogo",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={ "codigo", "nombre" },
     *              @OA\Property(property="codigo", type="string", maxLength=60),
     *              @OA\Property(property="nombre", type="string", maxLength=150),
     *              @OA\Property(property="descripcion", type="string", maxLength=255),
     *              @OA\Property(property="activo", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Registro creado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Registro creado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/LcsCatalogoSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function store(StoreCatalogoRequest $request)
    {
        $model = $this->service->store($request->validated());

        return $this->responseSuccess(
            'Registro creado correctamente',
            new LcsCatalogoResource($model)
        );
    }

    /**
     * @OA\Put(
     *      path="/api/lcs_catalogo/{id}",
     *      tags={"lcs_catalogo"},
     *      summary="Actualizar registro de catalogo",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="codigo", type="string", maxLength=60),
     *              @OA\Property(property="nombre", type="string", maxLength=150),
     *              @OA\Property(property="descripcion", type="string", maxLength=255),
     *              @OA\Property(property="activo", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Registro actualizado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro actualizado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/LcsCatalogoSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado"),
     *      @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function update(UpdateCatalogoRequest $request, LcsCatalogo $lcs_catalogo)
    {
        $model = $this->service->update($lcs_catalogo, $request->validated());

        return $this->responseSuccess(
            'Registro actualizado correctamente',
            new LcsCatalogoResource($model)
        );
    }

    /**
     * @OA\Delete(
     *      path="/api/lcs_catalogo/{id}",
     *      tags={"lcs_catalogo"},
     *      summary="Eliminar registro de catalogo",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Registro eliminado correctamente",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Registro eliminado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/LcsCatalogoSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Registro no encontrado")
     * )
     */
    public function destroy(LcsCatalogo $lcs_catalogo)
    {
        $deleted = $this->service->destroy($lcs_catalogo);

        return $this->responseSuccess(
            'Registro eliminado correctamente',
            new LcsCatalogoResource($deleted)
        );
    }
}
