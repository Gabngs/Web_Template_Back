<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawUsuarioTinySchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="nombre", type="string"),
 *     @OA\Property(property="apellidos", type="string", nullable=true),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="codigo", type="string")
 * )
 */
class SiawUsuarioTinyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellidos' => $this->apellidos,
            'email' => $this->email,
            'codigo' => $this->codigo,
        ];
    }
}
