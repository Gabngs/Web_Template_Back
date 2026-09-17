<?php

namespace App\Http\Requests\Siaw\SiawContentPermisos;

use App\Http\Requests\Siaw\Traits\SiawContentPermisosRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    use SiawContentPermisosRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_map(
            fn($rules) => array_merge(['required'], $rules),
            $this->fieldRules()
        );
    }
}
