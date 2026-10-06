<?php

namespace App\Http\Resources\Lcs;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="LcsCatalogoTinySchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="descripcion", type="string")
 * )
 */
class LcsCatalogoTinyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'descripcion' => $this->descripcion,
        ];
    }
}
