<?php

namespace App\Http\Requests\Siaw\Traits;

use Illuminate\Validation\Rule;

trait SiawSistemasRules
{
    protected function fieldRules(?string $ignoreId = null): array
    {
        $uniqueCodigo = Rule::unique('dbsiaw.siaw_sistemas', 'codigo')->withoutTrashed();
        if ($ignoreId) {
            $uniqueCodigo = $uniqueCodigo->ignore($ignoreId, 'id');
        }

        return [
            'codigo'      => ['string', 'max:20', $uniqueCodigo],
            'descripcion' => ['string', 'max:100'],
            'activo'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required'      => 'El código del sistema es requerido.',
            'codigo.unique'        => 'Ese código ya existe.',
            'descripcion.required' => 'La descripción es requerida.',
        ];
    }
}
