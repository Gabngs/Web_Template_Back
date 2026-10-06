<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawContentModelTinySchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="app_label", type="string"),
 *     @OA\Property(property="app_model", type="string"),
 *     @OA\Property(property="nombre_display", type="string")
 * )
 */
class SiawContentModelTinyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'app_label' => $this->app_label,
            'app_model' => $this->app_model,
            'nombre_display' => $this->nombre_display,
        ];
    }
}
