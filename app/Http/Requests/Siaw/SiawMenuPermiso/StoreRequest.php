<?php

namespace App\Http\Requests\Siaw\SiawMenuPermiso;

use App\Http\Requests\Siaw\Traits\SiawMenuPermisoRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    use SiawMenuPermisoRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_map(
            fn ($rules) => array_merge(['required'], $rules),
            $this->fieldRules()
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $this->assertCombinacionUnica($v);
            $this->assertMenuUnico($v);
        });
    }
}
