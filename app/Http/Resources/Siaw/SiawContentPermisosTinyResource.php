<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawContentPermisosTinySchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="codename", type="string"),
 *     @OA\Property(property="desc", type="string")
 * )
 */
class SiawContentPermisosTinyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'codename' => $this->codename,
            'desc' => $this->desc,
        ];
    }
}
