<?php

namespace App\Http\Resources\Lcs;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Lcs\LcsCatalogoRelationResource;
use App\Http\Resources\Siaw\SiawUsuarioRelationResource;

/**
 * @OA\Schema(
 *     schema="LcsCatalogoDetSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="catalogo", ref="#/components/schemas/LcsCatalogoRelationSchema", nullable=true),
 *     @OA\Property(property="codigo", type="string", maxLength=60),
 *     @OA\Property(property="abreviatura", type="string", maxLength=20, nullable=true),
 *     @OA\Property(property="nombre", type="string", maxLength=150),
 *     @OA\Property(property="descripcion", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="valor_numerico", type="number", format="float", nullable=true),
 *     @OA\Property(property="valor_texto", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="activo", type="boolean"),
 *     @OA\Property(property="created_by", ref="#/components/schemas/SiawUsuarioRelationSchema", nullable=true),
 *     @OA\Property(property="updated_by", ref="#/components/schemas/SiawUsuarioRelationSchema", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class LcsCatalogoDetResource extends JsonResource
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
            'valor_numerico' => $this->valor_numerico,
            'valor_texto' => $this->valor_texto,
            'activo' => (bool) $this->activo,
            'created_by' => new SiawUsuarioRelationResource($this->whenLoaded('created_by')),
            'updated_by' => new SiawUsuarioRelationResource($this->whenLoaded('updated_by')),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->format('Y-m-d H:i:s') : null,
        ];
    }
}
