<?php

namespace App\Http\Requests\Siaw\SiawSistemas;

use App\Http\Requests\Siaw\Traits\SiawSistemasRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    use SiawSistemasRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_map(
            fn($rules) => array_merge(['sometimes'], $rules),
            $this->fieldRules($this->route('siaw_sistema')?->id)
        );
    }
}
