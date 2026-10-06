<?php

namespace App\Http\Resources\Lcs;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="LcsCatalogoRelationSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="codigo", type="string"),
 *     @OA\Property(property="nombre", type="string"),
 *     @OA\Property(property="descripcion", type="string")
 * )
 */
class LcsCatalogoRelationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
        ];
    }
}
