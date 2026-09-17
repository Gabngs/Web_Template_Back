<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawPermisoUsuarioSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="usuario", ref="#/components/schemas/SiawUsuarioRelationSchema", nullable=true),
 *     @OA\Property(property="permiso", ref="#/components/schemas/SiawContentPermisosRelationSchema", nullable=true),
 *     @OA\Property(property="permitido", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class SiawPermisoUsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'usuario'    => $this->whenLoaded('usuario', fn() => new SiawUsuarioRelationResource($this->usuario)),
            'permiso'    => $this->whenLoaded('permiso', fn() => new SiawContentPermisosRelationResource($this->permiso)),
            'permitido'  => (bool) $this->permitido,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
