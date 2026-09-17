<?php

namespace App\Http\Requests\Siaw\Traits;

use Illuminate\Validation\Rule;

trait SiawContentModelRules
{
    protected function fieldRules(?string $ignoreId = null): array
    {
        $uniqueModel = Rule::unique('dbsiaw.siaw_content_model', 'app_model')->withoutTrashed();
        if ($ignoreId) {
            $uniqueModel = $uniqueModel->ignore($ignoreId, 'id');
        }

        return [
            'app_label'      => ['string', 'max:100'],
            'app_model'      => ['string', 'max:100', $uniqueModel],
            'nombre_display' => ['string', 'max:255'],
            'sistema_id'     => ['string', 'exists:dbsiaw.siaw_sistemas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'app_label.required'      => 'El label de la app es requerido.',
            'app_model.required'      => 'El modelo es requerido.',
            'app_model.unique'        => 'El modelo ya existe.',
            'nombre_display.required' => 'El nombre de display es requerido.',
            'sistema_id.required'     => 'El sistema es requerido.',
            'sistema_id.exists'       => 'El sistema seleccionado no existe.',
        ];
    }
}
