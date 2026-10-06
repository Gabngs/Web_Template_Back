<?php

namespace App\Http\Requests\Siaw\Sistemas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSistemasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20', Rule::unique('dbsiaw.siaw_sistemas', 'codigo')->withoutTrashed()],
            'descripcion' => 'required|string|max:100',
            'activo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código del sistema es requerido.',
            'codigo.string' => 'El campo codigo debe ser texto',
            'codigo.max' => 'El campo codigo no puede superar 20 caracteres',
            'codigo.unique' => 'Ese código ya existe.',
            'descripcion.required' => 'La descripción es requerida.',
            'descripcion.string' => 'El campo descripcion debe ser texto',
            'descripcion.max' => 'El campo descripcion no puede superar 100 caracteres',
            'activo.boolean' => 'El campo activo debe ser verdadero o falso',
        ];
    }
}
