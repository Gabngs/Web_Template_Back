<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawMenusTinySchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="titulo", type="string"),
 *     @OA\Property(property="ruta", type="string", nullable=true)
 * )
 */
class SiawMenusTinyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'ruta' => $this->ruta,
        ];
    }
}
