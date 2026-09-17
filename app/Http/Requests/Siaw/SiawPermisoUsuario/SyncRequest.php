<?php

namespace App\Http\Requests\Siaw\SiawPermisoUsuario;

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
            'usuario_id'    => ['required', 'string', 'exists:dbsiaw.siaw_usuarios,id'],
            // "present" (no "required"): array vacío = "quitar todos los permisos
            // extra concedidos a este usuario" — estado válido del pick-list.
            'permiso_ids'   => ['present', 'array'],
            'permiso_ids.*' => ['string', 'exists:dbsiaw.siaw_content_permisos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario_id.required'  => 'El usuario es requerido.',
            'usuario_id.exists'    => 'El usuario no existe.',
            'permiso_ids.present'  => 'permiso_ids es requerido (puede ir vacío para quitar todos).',
            'permiso_ids.array'    => 'permiso_ids debe ser un array.',
            'permiso_ids.*.exists' => 'Uno de los permisos enviados no existe.',
        ];
    }
}
