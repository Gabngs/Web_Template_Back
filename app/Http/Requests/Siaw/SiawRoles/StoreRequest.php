<?php

namespace App\Http\Requests\Siaw\SiawRoles;

use App\Http\Requests\Siaw\Traits\SiawRolesRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    use SiawRolesRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fields = $this->fieldRules();

        return [
            'name'        => array_merge(['required'], $fields['name']),
            'slug'        => array_merge(['required'], $fields['slug']),
            'guard_name'  => array_merge(['sometimes'], $fields['guard_name']),
            'descripcion' => array_merge(['sometimes'], $fields['descripcion']),
            'activo'      => array_merge(['sometimes'], $fields['activo']),
        ];
    }
}
