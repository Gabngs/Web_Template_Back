<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawMenuPermisoSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="menu", ref="#/components/schemas/SiawMenusRelationSchema", nullable=true),
 *     @OA\Property(property="permiso", ref="#/components/schemas/SiawContentPermisosRelationSchema", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class SiawMenuPermisoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'menu'       => $this->whenLoaded('menu', fn() => new SiawMenusRelationResource($this->menu)),
            'permiso'    => $this->whenLoaded('permiso', fn() => new SiawContentPermisosRelationResource($this->permiso)),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
