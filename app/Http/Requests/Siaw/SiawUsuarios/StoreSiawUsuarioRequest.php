<?php

namespace App\Http\Requests\Siaw\SiawUsuarios;

use App\Http\Requests\Siaw\Traits\SiawUsuariosRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSiawUsuarioRequest extends FormRequest
{
    use SiawUsuariosRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = $this->fieldRules();

        $rules['nombre'] = array_merge(['required'], $rules['nombre']);
        $rules['email']  = array_merge(['required'], $rules['email']);

        return $rules;
    }
}
