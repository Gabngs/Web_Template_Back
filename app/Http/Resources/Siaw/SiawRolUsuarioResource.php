<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawRolUsuarioSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="usuario", ref="#/components/schemas/SiawUsuarioRelationSchema", nullable=true),
 *     @OA\Property(property="rol", ref="#/components/schemas/SiawRolRelationSchema", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class SiawRolUsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'usuario'    => $this->whenLoaded('usuario', fn() => new SiawUsuarioRelationResource($this->usuario)),
            'rol'        => $this->whenLoaded('rol', fn() => new SiawRolRelationResource($this->rol)),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
