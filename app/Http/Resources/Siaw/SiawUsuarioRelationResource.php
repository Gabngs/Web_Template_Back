<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawUsuarioRelationSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="nombre", type="string"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="codigo", type="string")
 * )
 */
class SiawUsuarioRelationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'     => $this->id,
            'nombre' => $this->nombre,
            'email'  => $this->email,
            'codigo' => $this->codigo,
        ];
    }
}
