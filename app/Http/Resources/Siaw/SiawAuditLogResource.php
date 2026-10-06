<?php

namespace App\Http\Resources\Siaw;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Siaw\SiawUsuarioRelationResource;

/**
 * @OA\Schema(
 *     schema="SiawAuditLogSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="nombre_proceso", type="string", maxLength=255),
 *     @OA\Property(property="tipo_proceso", type="integer"),
 *     @OA\Property(property="estado_proceso", type="integer"),
 *     @OA\Property(property="origen", type="integer"),
 *     @OA\Property(property="modelo_afectado", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="registro_id", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="input", type="object", nullable=true),
 *     @OA\Property(property="output", type="object", nullable=true),
 *     @OA\Property(property="error", type="string", nullable=true),
 *     @OA\Property(property="created_by", ref="#/components/schemas/SiawUsuarioRelationSchema", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class SiawAuditLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nombre_proceso' => $this->nombre_proceso,
            'tipo_proceso' => $this->tipo_proceso,
            'estado_proceso' => $this->estado_proceso,
            'origen' => $this->origen,
            'modelo_afectado' => $this->modelo_afectado,
            'registro_id' => $this->registro_id,
            'input' => $this->input,
            'output' => $this->output,
            'error' => $this->error,
            'created_by' => new SiawUsuarioRelationResource($this->whenLoaded('created_by')),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->format('Y-m-d H:i:s') : null,
        ];
    }
}
