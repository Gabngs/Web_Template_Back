<?php

namespace App\Http\Requests\Siaw\SiawPermisoUsuario;

use App\Http\Requests\Siaw\Traits\SiawPermisoUsuarioRules;
use App\Models\dbsiaw\SiawContentPermisos;
use App\Models\dbsiaw\SiawPermisoUsuario;
use App\Models\dbsiaw\SiawUsuarios;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRequest extends FormRequest
{
    use SiawPermisoUsuarioRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $fields = $this->fieldRules();

        return [
            'permiso_id' => array_merge(['required'], $fields['permiso_id']),
            'usuario_id' => array_merge(['required'], $fields['usuario_id']),
            'permitido'  => array_merge(['sometimes'], $fields['permitido']),
        ];
    }

    // siaw_permiso_usuario tiene unique(permiso_id, usuario_id) en la DB, pero
    // esos campos guardan pkid mientras el request trae UUID — un unique:tabla
    // normal no puede comparar UUID contra pkid, así que se resuelve acá para
    // devolver 422 en vez de dejar que la QueryException del unique llegue cruda.
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('permiso_id') || $validator->errors()->has('usuario_id')) {
                return;
            }

            $permisoPkid = SiawContentPermisos::where('id', $this->permiso_id)->value('pkid');
            $usuarioPkid = SiawUsuarios::where('id', $this->usuario_id)->value('pkid');

            $yaAsignado = SiawPermisoUsuario::where('permiso_id', $permisoPkid)
                ->where('usuario_id', $usuarioPkid)
                ->exists();

            if ($yaAsignado) {
                $validator->errors()->add('usuario_id', 'Este usuario ya tiene ese permiso asignado.');
            }
        });
    }
}
