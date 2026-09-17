<?php

namespace App\Http\Requests\Siaw\SiawPermisoRol;

use App\Http\Requests\Siaw\Traits\SiawPermisoRolRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    use SiawPermisoRolRules;

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
