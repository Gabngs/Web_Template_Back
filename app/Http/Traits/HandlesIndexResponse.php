<?php

namespace App\Http\Traits;

/**
 * Respuesta del `index` de los controllers: la misma acción sirve con y sin paginación.
 *
 * - Sin `?paginate=true`: `Resource::collection($data)`.
 * - Con `?paginate=true`: solo los items en `data` y un bloque `meta` al mismo nivel
 *   (current_page, last_page, per_page, total, from, to) -- el que consume el frontend.
 *
 * Requiere que el controller use `Essa\APIToolKit\Api\ApiResponse` (responseSuccess).
 */
trait HandlesIndexResponse
{
    protected function responseIndex(
        mixed $data,
        string $resourceClass,
        bool $paginate,
        string $message = 'Registros obtenidos correctamente'
    ) {
        if (! $paginate) {
            return $this->responseSuccess($message, $resourceClass::collection($data));
        }

        // $data es LengthAwarePaginator: no pasarlo tal cual a responseSuccess() (produce data.data).
        $response = $this->responseSuccess($message, $resourceClass::collection($data->items()));

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
}
