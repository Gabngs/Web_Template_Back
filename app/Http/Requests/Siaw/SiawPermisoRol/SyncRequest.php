<?php

namespace App\Http\Requests\Siaw\SiawPermisoRol;

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
            'rol_id'        => ['required', 'string', 'exists:dbsiaw.siaw_roles,id'],
            // "present" (no "required") porque un array vacío es válido: significa
            // "quitar todos los permisos de este rol" — un pick-list que queda con
            // "Asignados" en blanco es un estado legítimo, no un error de validación.
            'permiso_ids'   => ['present', 'array'],
            'permiso_ids.*' => ['string', 'exists:dbsiaw.siaw_content_permisos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'rol_id.required'      => 'El rol es requerido.',
            'rol_id.exists'        => 'El rol no existe.',
            'permiso_ids.present'  => 'permiso_ids es requerido (puede ir vacío para quitar todos).',
            'permiso_ids.array'    => 'permiso_ids debe ser un array.',
            'permiso_ids.*.exists' => 'Uno de los permisos enviados no existe.',
        ];
    }
}
