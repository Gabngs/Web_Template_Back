<?php

namespace App\Http\Requests\Siaw\SiawSistemas;

use App\Http\Requests\Siaw\Traits\SiawSistemasRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    use SiawSistemasRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fields = $this->fieldRules();

        return [
            'codigo'      => array_merge(['required'], $fields['codigo']),
            'descripcion' => array_merge(['required'], $fields['descripcion']),
            'activo'      => array_merge(['sometimes'], $fields['activo']),
        ];
    }
}
