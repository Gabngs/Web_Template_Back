<?php

namespace App\Http\Resources\Lcs;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Lcs\LcsCatalogoRelationResource;

/**
 * @OA\Schema(
 *     schema="LcsCatalogoDetRelationSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="catalogo", ref="#/components/schemas/LcsCatalogoRelationSchema", nullable=true),
 *     @OA\Property(property="codigo", type="string"),
 *     @OA\Property(property="abreviatura", type="string"),
 *     @OA\Property(property="nombre", type="string"),
 *     @OA\Property(property="descripcion", type="string")
 * )
 */
class LcsCatalogoDetRelationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'catalogo' => $this->whenLoaded('lcs_catalogo', fn () => new LcsCatalogoRelationResource($this->lcs_catalogo)),
            'codigo' => $this->codigo,
            'abreviatura' => $this->abreviatura,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
        ];
    }
}
