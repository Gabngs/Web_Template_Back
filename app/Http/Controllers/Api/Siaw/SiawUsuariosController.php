<?php

namespace App\Http\Controllers\Api\Siaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siaw\SiawUsuarios\StoreSiawUsuarioRequest;
use App\Http\Requests\Siaw\SiawUsuarios\UpdateSiawUsuarioRequest;
use App\Http\Resources\Siaw\SiawUsuarioResource;
use App\Models\dbsiaw\SiawUsuarios;
use App\Services\Siaw\SiawUsuariosService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Usuarios")
 */
class SiawUsuariosController extends Controller
{
    use ApiResponse;

    public function __construct(protected SiawUsuariosService $service) {}

    /**
     * @OA\Get(
     *      path="/api/siaw_usuarios",
     *      operationId="indexSiawUsuarios",
     *      tags={"Usuarios"},
     *      summary="Listar usuarios del sistema",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="paginate", in="query", @OA\Schema(type="boolean")),
     *      @OA\Response(
     *          response=200,
     *          description="Usuarios registrados",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Usuarios obtenidos correctamente"),
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SiawUsuarioSchema"))
     *          )
     *      )
     * )
     */
    public function index(Request $request)
    {
        $paginate = $request->boolean('paginate');
        $data     = $this->service->index($paginate);

        if (! $paginate) {
            return $this->responseSuccess(
                'Usuarios obtenidos correctamente',
                SiawUsuarioResource::collection($data)
            );
        }

        // $data es un LengthAwarePaginator: se transforman solo los items (array
        // plano) y la metadata de paginación viaja en 'meta', sibling de 'data' —
        // ver ApiResponse#Paginación sin duplicar `data`.
        $response = $this->responseSuccess(
            'Usuarios obtenidos correctamente',
            SiawUsuarioResource::collection($data->items())
        );

        return $response->setData(array_merge($response->getData(true), [
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
                'from'         => $data->firstItem(),
                'to'           => $data->lastItem(),
            ],
        ]));
    }

    /**
     * @OA\Get(
     *      path="/api/siaw_usuarios/{siaw_usuario}",
     *      operationId="showSiawUsuario",
     *      tags={"Usuarios"},
     *      summary="Ver un usuario por UUID",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_usuario", in="path", required=true, @OA\Schema(type="string")),
     *      @OA\Response(
     *          response=200,
     *          description="Usuario encontrado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Usuario obtenido correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawUsuarioSchema")
     *          )
     *      ),
     *      @OA\Response(response=404, description="No encontrado")
     * )
     */
    public function show(SiawUsuarios $siaw_usuario)
    {
        $model = $this->service->show($siaw_usuario);

        return $this->responseSuccess(
            'Usuario obtenido correctamente',
            new SiawUsuarioResource($model)
        );
    }

    /**
     * @OA\Post(
     *      path="/api/siaw_usuarios",
     *      operationId="storeSiawUsuario",
     *      tags={"Usuarios"},
     *      summary="Registrar un nuevo usuario — solo superuser/admin",
     *      description="El backend genera un password temporal aleatorio y lo envía por correo — no se recibe ni se devuelve en texto plano.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"nombre","email"},
     *              @OA\Property(property="nombre", type="string"),
     *              @OA\Property(property="apellidos", type="string"),
     *              @OA\Property(property="email", type="string", format="email"),
     *              @OA\Property(property="rol_id", type="string", format="uuid")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Usuario creado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=201),
     *              @OA\Property(property="message", type="string", example="Usuario creado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawUsuarioSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(StoreSiawUsuarioRequest $request)
    {
        $model = $this->service->store($request->validated());

        return $this->responseCreated(
            'Usuario creado correctamente',
            new SiawUsuarioResource($model)
        );
    }

    /**
     * @OA\Put(
     *      path="/api/siaw_usuarios/{siaw_usuario}",
     *      operationId="updateSiawUsuario",
     *      tags={"Usuarios"},
     *      summary="Actualizar un usuario — solo superuser/admin",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_usuario", in="path", required=true, @OA\Schema(type="string")),
     *      @OA\Response(
     *          response=200,
     *          description="Usuario actualizado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Usuario actualizado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawUsuarioSchema")
     *          )
     *      ),
     *      @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function update(UpdateSiawUsuarioRequest $request, SiawUsuarios $siaw_usuario)
    {
        $model = $this->service->update($siaw_usuario, $request->validated());

        return $this->responseSuccess(
            'Usuario actualizado correctamente',
            new SiawUsuarioResource($model)
        );
    }

    /**
     * @OA\Delete(
     *      path="/api/siaw_usuarios/{siaw_usuario}",
     *      operationId="destroySiawUsuario",
     *      tags={"Usuarios"},
     *      summary="Eliminar (soft-delete) un usuario — solo superuser/admin",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(name="siaw_usuario", in="path", required=true, @OA\Schema(type="string")),
     *      @OA\Response(
     *          response=200,
     *          description="Usuario eliminado",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="integer", example=200),
     *              @OA\Property(property="message", type="string", example="Usuario eliminado correctamente"),
     *              @OA\Property(property="data", ref="#/components/schemas/SiawUsuarioSchema")
     *          )
     *      )
     * )
     */
    public function destroy(SiawUsuarios $siaw_usuario)
    {
        $deleted = $this->service->destroy($siaw_usuario);

        return $this->responseSuccess(
            'Usuario eliminado correctamente',
            new SiawUsuarioResource($deleted)
        );
    }
}
