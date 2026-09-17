<?php

namespace App\Http\Requests\Siaw\SiawRolUsuario;

use Illuminate\Foundation\Http\FormRequest;

class SyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usuario_id' => ['required', 'string', 'exists:dbsiaw.siaw_usuarios,id'],
            // "present" (no "required"): array vacío = "quitar todos los roles
            // de este usuario" — estado válido del pick-list.
            'rol_ids'    => ['present', 'array'],
            'rol_ids.*'  => ['string', 'exists:dbsiaw.siaw_roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario_id.required' => 'El usuario es requerido.',
            'usuario_id.exists'   => 'El usuario no existe.',
            'rol_ids.present'     => 'rol_ids es requerido (puede ir vacío para quitar todos).',
            'rol_ids.array'       => 'rol_ids debe ser un array.',
            'rol_ids.*.exists'    => 'Uno de los roles enviados no existe.',
        ];
    }
}
