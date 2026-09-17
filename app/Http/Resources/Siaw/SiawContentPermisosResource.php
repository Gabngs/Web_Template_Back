<?php

namespace App\Http\Resources\Siaw;

use Illuminate\Http\Request;
use App\Http\Resources\Siaw\SiawContentModelRelationResource;
use App\Http\Resources\Siaw\SiawSistemasRelationResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SiawContentPermisosSchema",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="codename", type="string"),
 *     @OA\Property(property="desc", type="string"),
 *     @OA\Property(property="content_model", ref="#/components/schemas/SiawContentModelRelationSchema", nullable=true),
 *     @OA\Property(property="sistema", ref="#/components/schemas/SiawSistemasRelationSchema", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class SiawContentPermisosResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'codename'      => $this->codename,
            'desc'          => $this->desc,
            'content_model' => $this->whenLoaded('contentModel', fn() => new SiawContentModelRelationResource($this->contentModel)),
            'sistema'       => $this->whenLoaded('sistema', fn() => new SiawSistemasRelationResource($this->sistema)),
            'created_at'    => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'    => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
