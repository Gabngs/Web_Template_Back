<?php

namespace App\Http\Requests\Siaw\Traits;

use Illuminate\Validation\Rule;

trait SiawContentPermisosRules
{
    protected function fieldRules(?string $ignoreId = null): array
    {
        $uniqueCodename = Rule::unique('dbsiaw.siaw_content_permisos', 'codename')->withoutTrashed();
        if ($ignoreId) {
            $uniqueCodename = $uniqueCodename->ignore($ignoreId, 'id');
        }

        return [
            'content_model_id' => ['string', 'exists:dbsiaw.siaw_content_model,id'],
            'codename'         => ['string', 'max:100', $uniqueCodename],
            'desc'             => ['string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'content_model_id.required' => 'El modelo de contenido es requerido.',
            'content_model_id.exists'   => 'El modelo de contenido no existe.',
            'codename.required'         => 'El codename es requerido.',
            'codename.unique'           => 'El codename ya existe.',
            'desc.required'             => 'La descripción es requerida.',
        ];
    }
}
