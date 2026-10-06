<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Lcs\LcsCatalogoDetRelationResource;

/**
 * @OA\Schema(
 *     schema="SiawParametrosRelationSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="codigo", type="string"),
 *     @OA\Property(property="descripcion", type="string"),
 *     @OA\Property(property="valor", type="string"),
 *     @OA\Property(property="catalogo_det", ref="#/components/schemas/LcsCatalogoDetRelationSchema", nullable=true)
 * )
 */
class SiawParametrosRelationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'valor' => $this->valor,
            'catalogo_det' => $this->whenLoaded('lcs_catalogo_det', fn () => new LcsCatalogoDetRelationResource($this->lcs_catalogo_det)),
        ];
    }
}
