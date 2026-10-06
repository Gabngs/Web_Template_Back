<?php

namespace App\Http\Resources\Siaw;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Lcs\LcsCatalogoDetRelationResource;
use App\Http\Resources\Siaw\SiawSistemasRelationResource;
use App\Http\Resources\Siaw\SiawUsuarioRelationResource;

/**
 * @OA\Schema(
 *     schema="SiawParametrosSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="sistemas", ref="#/components/schemas/SiawSistemasRelationSchema", nullable=true),
 *     @OA\Property(property="codigo", type="string", maxLength=80),
 *     @OA\Property(property="descripcion", type="string", maxLength=255),
 *     @OA\Property(property="valor", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="catalogo_det", ref="#/components/schemas/LcsCatalogoDetRelationSchema", nullable=true),
 *     @OA\Property(property="activo", type="boolean"),
 *     @OA\Property(property="created_by", ref="#/components/schemas/SiawUsuarioRelationSchema", nullable=true),
 *     @OA\Property(property="updated_by", ref="#/components/schemas/SiawUsuarioRelationSchema", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class SiawParametrosResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sistemas' => $this->whenLoaded('siaw_sistemas', fn () => new SiawSistemasRelationResource($this->siaw_sistemas)),
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'valor' => $this->valor,
            'catalogo_det' => $this->whenLoaded('lcs_catalogo_det', fn () => new LcsCatalogoDetRelationResource($this->lcs_catalogo_det)),
            'activo' => (bool) $this->activo,
            'created_by' => new SiawUsuarioRelationResource($this->whenLoaded('created_by')),
            'updated_by' => new SiawUsuarioRelationResource($this->whenLoaded('updated_by')),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->format('Y-m-d H:i:s') : null,
        ];
    }
}
