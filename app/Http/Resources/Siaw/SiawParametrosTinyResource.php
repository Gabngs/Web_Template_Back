<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawParametrosTinySchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="codigo", type="string"),
 *     @OA\Property(property="descripcion", type="string"),
 *     @OA\Property(property="valor", type="string")
 * )
 */
class SiawParametrosTinyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'valor' => $this->valor,
        ];
    }
}
