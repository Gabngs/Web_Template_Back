<?php

namespace App\Http\Requests\Siaw\Traits;

trait SiawMenusRules
{
    protected function fieldRules(): array
    {
        return [
            'sistema_id'  => ['string', 'exists:dbsiaw.siaw_sistemas,id'],
            'parent_id'   => ['nullable', 'string', 'exists:dbsiaw.siaw_menus,id'],
            'titulo'      => ['string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'ruta'        => ['nullable', 'string', 'max:255'],
            'nombre_icon' => ['nullable', 'string', 'max:100'],
            'orden'       => ['integer', 'min:0'],
            'activo'      => ['boolean'],
            'dashboard'   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sistema_id.required' => 'El sistema es requerido.',
            'sistema_id.exists'   => 'El sistema no existe.',
            'parent_id.exists'    => 'El menú padre no existe.',
            'titulo.required'     => 'El título es requerido.',
        ];
    }
}
