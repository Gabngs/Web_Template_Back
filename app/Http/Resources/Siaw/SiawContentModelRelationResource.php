<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawContentModelRelationSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="app_label", type="string"),
 *     @OA\Property(property="app_model", type="string")
 * )
 */
class SiawContentModelRelationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'app_label' => $this->app_label,
            'app_model' => $this->app_model,
        ];
    }
}
