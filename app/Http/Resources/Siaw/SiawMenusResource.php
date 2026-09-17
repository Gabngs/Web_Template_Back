<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawMenusSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="titulo", type="string"),
 *     @OA\Property(property="descripcion", type="string", nullable=true),
 *     @OA\Property(property="ruta", type="string", nullable=true),
 *     @OA\Property(property="nombre_icon", type="string", nullable=true),
 *     @OA\Property(property="orden", type="integer"),
 *     @OA\Property(property="activo", type="boolean"),
 *     @OA\Property(property="dashboard", type="boolean"),
 *     @OA\Property(property="clave", type="string", nullable=true),
 *     @OA\Property(property="sistema", ref="#/components/schemas/SiawSistemasRelationSchema", nullable=true),
 *     @OA\Property(property="parent", ref="#/components/schemas/SiawMenusRelationSchema", nullable=true),
 *     @OA\Property(property="hijos", type="array", @OA\Items(ref="#/components/schemas/SiawMenusRelationSchema")),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class SiawMenusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'titulo'      => $this->titulo,
            'descripcion' => $this->descripcion,
            'ruta'        => $this->ruta,
            'nombre_icon' => $this->nombre_icon,
            'orden'       => $this->orden,
            'activo'      => (bool) $this->activo,
            'dashboard'   => (bool) $this->dashboard,
            'clave'       => $this->clave,
            'sistema'     => $this->whenLoaded('sistema', fn() => new SiawSistemasRelationResource($this->sistema)),
            'parent'      => $this->whenLoaded('parent', fn() => new SiawMenusRelationResource($this->parent)),
            'hijos'       => SiawMenusRelationResource::collection($this->whenLoaded('hijos')),
            'created_at'  => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'  => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
