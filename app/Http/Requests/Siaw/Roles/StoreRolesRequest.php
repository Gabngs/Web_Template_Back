<?php

namespace App\Http\Requests\Siaw\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('dbsiaw.siaw_roles', 'name')->withoutTrashed()],
            'slug' => ['required', 'string', 'max:100', Rule::unique('dbsiaw.siaw_roles', 'slug')->withoutTrashed()],
            'guard_name' => 'sometimes|string|max:50',
            'descripcion' => 'sometimes|string|max:255',
            'activo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del rol es requerido.',
            'name.string' => 'El campo name debe ser texto',
            'name.max' => 'El campo name no puede superar 100 caracteres',
            'name.unique' => 'El nombre del rol ya existe.',
            'slug.required' => 'El slug es requerido.',
            'slug.string' => 'El campo slug debe ser texto',
            'slug.max' => 'El campo slug no puede superar 100 caracteres',
            'slug.unique' => 'El slug ya existe.',
            'guard_name.string' => 'El campo guard_name debe ser texto',
            'guard_name.max' => 'El campo guard_name no puede superar 50 caracteres',
            'descripcion.string' => 'El campo descripcion debe ser texto',
            'descripcion.max' => 'El campo descripcion no puede superar 255 caracteres',
            'activo.boolean' => 'El campo activo debe ser verdadero o falso',
        ];
    }
}
