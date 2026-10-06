<?php

namespace App\Http\Requests\Lcs\Catalogo;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => 'required|string|max:60|unique:dblcs.lcs_catalogo,codigo',
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El campo codigo es requerido',
            'codigo.string' => 'El campo codigo debe ser texto',
            'codigo.max' => 'El campo codigo no puede superar 60 caracteres',
            'codigo.unique' => 'El valor de codigo ya existe',
            'nombre.required' => 'El campo nombre es requerido',
            'nombre.string' => 'El campo nombre debe ser texto',
            'nombre.max' => 'El campo nombre no puede superar 150 caracteres',
            'descripcion.string' => 'El campo descripcion debe ser texto',
            'descripcion.max' => 'El campo descripcion no puede superar 255 caracteres',
            'activo.boolean' => 'El campo activo debe ser verdadero o falso',
        ];
    }
}
