<?php

namespace App\Http\Requests\Siaw\SiawMenus;

use App\Http\Requests\Siaw\Traits\SiawMenusRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    use SiawMenusRules;

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
