<?php

namespace App\Http\Requests\Lcs\Catalogo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $modelId = $this->route('lcs_catalogo')?->pkid;

        return [
            'codigo' => "sometimes|string|max:60|unique:dblcs.lcs_catalogo,codigo,{$modelId},pkid",
            'nombre' => "sometimes|string|max:150",
            'descripcion' => "nullable|string|max:255",
            'activo' => "sometimes|boolean",
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.string' => 'El campo codigo debe ser texto',
            'codigo.max' => 'El campo codigo no puede superar 60 caracteres',
            'codigo.unique' => 'El valor de codigo ya existe',
            'nombre.string' => 'El campo nombre debe ser texto',
            'nombre.max' => 'El campo nombre no puede superar 150 caracteres',
            'descripcion.string' => 'El campo descripcion debe ser texto',
            'descripcion.max' => 'El campo descripcion no puede superar 255 caracteres',
            'activo.boolean' => 'El campo activo debe ser verdadero o falso',
        ];
    }
}
