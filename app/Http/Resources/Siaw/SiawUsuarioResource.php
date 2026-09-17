<?php

namespace App\Http\Resources\Siaw;

use App\Http\Resources\Siaw\SiawRolRelationResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawUsuarioSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="nombre", type="string"),
 *     @OA\Property(property="apellidos", type="string"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="codigo", type="string", description="Código autogenerado de 5 dígitos"),
 *     @OA\Property(property="activo", type="boolean"),
 *     @OA\Property(property="debe_cambiar_password", type="boolean"),
 *     @OA\Property(property="ultimo_acceso_en", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="rol", ref="#/components/schemas/SiawRolRelationSchema", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class SiawUsuarioResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id, // UUID — nunca exponer pkid
            'nombre'                => $this->nombre,
            'apellidos'             => $this->apellidos,
            'email'                 => $this->email,
            'codigo'                => $this->codigo,
            'activo'                => (bool) $this->activo,
            'debe_cambiar_password' => (bool) $this->debe_cambiar_password,
            'ultimo_acceso_en'      => $this->ultimo_acceso_en?->toDateTimeString(),
            'rol'                   => $this->whenLoaded('rol', fn() =>
                new SiawRolRelationResource($this->rol)
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
