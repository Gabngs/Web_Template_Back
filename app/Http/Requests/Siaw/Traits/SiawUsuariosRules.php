<?php

namespace App\Http\Requests\Siaw\Traits;

use Illuminate\Validation\Rule;

trait SiawUsuariosRules
{
    protected function fieldRules(?string $ignoreId = null): array
    {
        $uniqueEmail = Rule::unique('dbsiaw.siaw_usuarios', 'email')->withoutTrashed();
        if ($ignoreId) {
            $uniqueEmail = $uniqueEmail->ignore($ignoreId, 'id');
        }

        return [
            'nombre'    => ['string', 'max:100'],
            'apellidos' => ['nullable', 'string', 'max:100'],
            'email'     => ['string', 'max:150', $uniqueEmail],
            'rol_id'    => ['nullable', 'uuid', 'exists:dbsiaw.siaw_roles,id'],
            'activo'    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'   => 'El nombre es requerido.',
            'email.required'    => 'El email es requerido.',
            'email.unique'      => 'Ya existe un usuario con ese email.',
            'rol_id.exists'     => 'El rol seleccionado no existe.',
        ];
    }
}
