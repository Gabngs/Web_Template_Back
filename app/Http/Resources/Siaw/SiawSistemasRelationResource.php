<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawSistemasRelationSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="codigo", type="string"),
 *     @OA\Property(property="descripcion", type="string")
 * )
 */
class SiawSistemasRelationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'codigo'      => $this->codigo,
            'descripcion' => $this->descripcion,
        ];
    }
}
