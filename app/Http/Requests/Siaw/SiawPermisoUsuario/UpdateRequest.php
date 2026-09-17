<?php

namespace App\Http\Requests\Siaw\SiawPermisoUsuario;

use App\Http\Requests\Siaw\Traits\SiawPermisoUsuarioRules;
use App\Models\dbsiaw\SiawContentPermisos;
use App\Models\dbsiaw\SiawPermisoUsuario;
use App\Models\dbsiaw\SiawUsuarios;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRequest extends FormRequest
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
            'permiso_id' => array_merge(['sometimes'], $fields['permiso_id']),
            'usuario_id' => array_merge(['sometimes'], $fields['usuario_id']),
            'permitido'  => array_merge(['sometimes'], $fields['permitido']),
        ];
    }

    // Mismo motivo que StoreRequest — solo corre si permiso_id/usuario_id
    // cambian, e ignora el propio registro (identificado por pkid, no id,
    // ver estándar "unique constraints — pkid vs id como ignore").
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->filled('permiso_id') && !$this->filled('usuario_id')) {
                return;
            }

            if ($validator->errors()->has('permiso_id') || $validator->errors()->has('usuario_id')) {
                return;
            }

            /** @var SiawPermisoUsuario $actual */
            $actual = $this->route('siaw_permiso_usuario');

            $permisoPkid = $this->filled('permiso_id')
                ? SiawContentPermisos::where('id', $this->permiso_id)->value('pkid')
                : $actual->permiso_id;

            $usuarioPkid = $this->filled('usuario_id')
                ? SiawUsuarios::where('id', $this->usuario_id)->value('pkid')
                : $actual->usuario_id;

            $yaAsignado = SiawPermisoUsuario::where('permiso_id', $permisoPkid)
                ->where('usuario_id', $usuarioPkid)
                ->where('pkid', '!=', $actual->pkid)
                ->exists();

            if ($yaAsignado) {
                $validator->errors()->add('usuario_id', 'Este usuario ya tiene ese permiso asignado.');
            }
        });
    }
}
