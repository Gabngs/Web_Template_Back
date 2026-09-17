<?php

namespace App\Http\Requests\Siaw\SiawMenus;

use App\Http\Requests\Siaw\Traits\SiawMenusRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    use SiawMenusRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fields = $this->fieldRules();

        return [
            'sistema_id'  => array_merge(['required'], $fields['sistema_id']),
            'parent_id'   => array_merge(['sometimes'], $fields['parent_id']),
            'titulo'      => array_merge(['required'], $fields['titulo']),
            'descripcion' => array_merge(['sometimes'], $fields['descripcion']),
            'ruta'        => array_merge(['sometimes'], $fields['ruta']),
            'nombre_icon' => array_merge(['sometimes'], $fields['nombre_icon']),
            'orden'       => array_merge(['sometimes'], $fields['orden']),
            'activo'      => array_merge(['sometimes'], $fields['activo']),
            'dashboard'   => array_merge(['sometimes'], $fields['dashboard']),
        ];
    }
}
