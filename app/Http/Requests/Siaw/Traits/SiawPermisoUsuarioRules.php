<?php

namespace App\Http\Requests\Siaw\Traits;

trait SiawPermisoUsuarioRules
{
    protected function fieldRules(): array
    {
        return [
            'permiso_id' => ['string', 'exists:dbsiaw.siaw_content_permisos,id'],
            'usuario_id' => ['string', 'exists:dbsiaw.siaw_usuarios,id'],
            'permitido'  => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'permiso_id.required' => 'El permiso es requerido.',
            'permiso_id.exists'   => 'El permiso no existe.',
            'usuario_id.required' => 'El usuario es requerido.',
            'usuario_id.exists'   => 'El usuario no existe.',
        ];
    }
}
