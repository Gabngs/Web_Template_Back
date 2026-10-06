<?php

namespace App\Http\Resources\Lcs;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="LcsCatalogoDetTinySchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="codigo", type="string"),
 *     @OA\Property(property="abreviatura", type="string"),
 *     @OA\Property(property="descripcion", type="string")
 * )
 */
class LcsCatalogoDetTinyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'abreviatura' => $this->abreviatura,
            'descripcion' => $this->descripcion,
        ];
    }
}
