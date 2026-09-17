<?php

namespace App\Http\Requests\Siaw\Traits;

trait SiawRolUsuarioRules
{
    protected function fieldRules(): array
    {
        return [
            'usuario_id' => ['string', 'exists:dbsiaw.siaw_usuarios,id'],
            'rol_id'     => ['string', 'exists:dbsiaw.siaw_roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario_id.required' => 'El usuario es requerido.',
            'usuario_id.exists'   => 'El usuario no existe.',
            'rol_id.required'     => 'El rol es requerido.',
            'rol_id.exists'       => 'El rol no existe.',
        ];
    }
}
