<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawMenusRelationSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="titulo", type="string"),
 *     @OA\Property(property="ruta", type="string", nullable=true),
 *     @OA\Property(property="nombre_icon", type="string", nullable=true),
 *     @OA\Property(property="orden", type="integer")
 * )
 */
class SiawMenusRelationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'titulo'      => $this->titulo,
            'ruta'        => $this->ruta,
            'nombre_icon' => $this->nombre_icon,
            'orden'       => $this->orden,
        ];
    }
}
