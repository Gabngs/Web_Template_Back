<?php

namespace App\Http\Resources\Lcs;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Siaw\SiawUsuarioRelationResource;

/**
 * @OA\Schema(
 *     schema="LcsCatalogoSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="codigo", type="string", maxLength=60),
 *     @OA\Property(property="nombre", type="string", maxLength=150),
 *     @OA\Property(property="descripcion", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="activo", type="boolean"),
 *     @OA\Property(property="created_by", ref="#/components/schemas/SiawUsuarioRelationSchema", nullable=true),
 *     @OA\Property(property="updated_by", ref="#/components/schemas/SiawUsuarioRelationSchema", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class LcsCatalogoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'activo' => (bool) $this->activo,
            'created_by' => new SiawUsuarioRelationResource($this->whenLoaded('created_by')),
            'updated_by' => new SiawUsuarioRelationResource($this->whenLoaded('updated_by')),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->format('Y-m-d H:i:s') : null,
        ];
    }
}
