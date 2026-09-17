<?php

namespace App\Http\Requests\Siaw\SiawRolUsuario;

use App\Http\Requests\Siaw\Traits\SiawRolUsuarioRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    use SiawRolUsuarioRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_map(
            fn($rules) => array_merge(['sometimes'], $rules),
            $this->fieldRules()
        );
    }
}
