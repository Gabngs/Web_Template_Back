<?php

namespace App\Http\Requests\Siaw\Traits;

trait SiawPermisoRolRules
{
    protected function fieldRules(): array
    {
        return [
            'permiso_id' => ['string', 'exists:dbsiaw.siaw_content_permisos,id'],
            'rol_id'     => ['string', 'exists:dbsiaw.siaw_roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'permiso_id.required' => 'El permiso es requerido.',
            'permiso_id.exists'   => 'El permiso no existe.',
            'rol_id.required'     => 'El rol es requerido.',
            'rol_id.exists'       => 'El rol no existe.',
        ];
    }
}
