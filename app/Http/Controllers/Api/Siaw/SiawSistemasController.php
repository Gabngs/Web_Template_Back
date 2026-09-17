<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siaw\SiawSistemas\StoreRequest;
use App\Http\Requests\Siaw\SiawSistemas\UpdateRequest;
use App\Http\Resources\Siaw\SiawSistemasResource;
use App\Models\dbsiaw\SiawSistemas;
use App\Services\Siaw\SiawSistemasService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Sistemas")
 */
class SiawSistemasController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SiawSistemasService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_sistemas",
     *      tags={"Sistemas"},
     *      summary="Sistemas (apps) que agrupan menús",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean")),
     *      @OA\Response(
     *          response=200,
     *          description="Sistemas registrados",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Sistemas obtenidos correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawSistemasSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->index($request->boolean('paginate'));

        return $this->responseSuccess('Sistemas obtenidos correctamente', SiawSistemasResource::collection($data));
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_sistemas/{siaw_sistema}",
     *      tags={"Sistemas"},
     *      summary="Ver un sistema por UUID",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_sistema", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Sistema encontrado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Sistema encontrado"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawSistemasSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="No encontrado")
     * )
     */
    public function show(SiawSistemas $siaw_sistema): JsonResponse
    {
        $model = $this->service->show($siaw_sistema);

        return $this->responseSuccess('Sistema encontrado', new SiawSistemasResource($model));
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_sistemas",
     *      tags={"Sistemas"},
     *      summary="Registrar un sistema",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"codigo","descripcion"},
     *              @OA\Property(property="codigo", type="string", maxLength=20, description="Único"),
     *              @OA\Property(property="descripcion", type="string", maxLength=100),
     *              @OA\Property(property="activo", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Sistema creado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Sistema creado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawSistemasSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated('Sistema creado correctamente', new SiawSistemasResource($model));
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_sistemas/{siaw_sistema}",
     *      tags={"Sistemas"},
     *      summary="Actualizar un sistema",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_sistema", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(property="codigo", type="string", maxLength=20, description="Único"),
     *              @OA\Property(property="descripcion", type="string", maxLength=100),
     *              @OA\Property(property="activo", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Sistema actualizado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Sistema actualizado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawSistemasSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function update(UpdateRequest $request, SiawSistemas $siaw_sistema): JsonResponse
    {
        $model = $this->service->update($siaw_sistema, $request->validated());

        return $this->responseSuccess('Sistema actualizado correctamente', new SiawSistemasResource($model));
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_sistemas/{siaw_sistema}",
     *      tags={"Sistemas"},
     *      summary="Eliminar un sistema",
     *      description="Solo superuser/admin.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_sistema", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *      @OA\Response(
     *          response=200,
     *          description="Sistema eliminado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Sistema eliminado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawSistemasSchema")
     *          )
     *      )
     * )
     */
    public function destroy(SiawSistemas $siaw_sistema): JsonResponse
    {
        $deleted = $this->service->destroy($siaw_sistema);

        return $this->responseSuccess('Sistema eliminado correctamente', new SiawSistemasResource($deleted));
    }
}
