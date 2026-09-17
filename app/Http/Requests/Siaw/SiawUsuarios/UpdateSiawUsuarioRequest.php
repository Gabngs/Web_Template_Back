<?php

namespace App\Http\Requests\Siaw\SiawUsuarios;

use App\Http\Requests\Siaw\Traits\SiawUsuariosRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiawUsuarioRequest extends FormRequest
{
    use SiawUsuariosRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // "sometimes" en todos los campos — el update permite enviar solo
        // los que cambian. El password nunca se acepta aquí (ver
        // AuthController::cambiarPassword / resetPassword).
        $ignoreId = $this->route('siaw_usuario')?->id;
        $rules    = $this->fieldRules($ignoreId);

        return array_map(fn($rule) => array_merge(['sometimes'], $rule), $rules);
    }
}
