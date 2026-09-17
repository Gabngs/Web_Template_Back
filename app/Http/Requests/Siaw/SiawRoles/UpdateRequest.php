<?php

namespace App\Http\Requests\Siaw\SiawRoles;

use App\Http\Requests\Siaw\Traits\SiawRolesRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    use SiawRolesRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_map(
            fn($rules) => array_merge(['sometimes'], $rules),
            $this->fieldRules($this->route('siaw_role')?->id)
        );
    }
}
