<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawContentModelSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="app_label", type="string"),
 *     @OA\Property(property="app_model", type="string"),
 *     @OA\Property(property="nombre_display", type="string"),
 *     @OA\Property(property="sistema", ref="#/components/schemas/SiawSistemasRelationSchema", nullable=true),
 *     @OA\Property(property="permisos", type="array", @OA\Items(ref="#/components/schemas/SiawContentPermisosRelationSchema")),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class SiawContentModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'app_label'      => $this->app_label,
            'app_model'      => $this->app_model,
            'nombre_display' => $this->nombre_display,
            'sistema'        => $this->whenLoaded('sistema', fn() => new SiawSistemasRelationResource($this->sistema)),
            'permisos'       => SiawContentPermisosRelationResource::collection(
                $this->whenLoaded('permisos')
            ),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
