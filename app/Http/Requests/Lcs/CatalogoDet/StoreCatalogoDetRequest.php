<?php

namespace App\Http\Requests\Lcs\CatalogoDet;

use App\Http\Requests\Lcs\Traits\LcsCatalogoDetRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogoDetRequest extends FormRequest
{
    use LcsCatalogoDetRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->getRelacionesRules('required'),
            [
                'codigo' => 'required|string|max:60',
                'abreviatura' => 'nullable|string|max:20',
                'nombre' => 'required|string|max:150',
                'descripcion' => 'nullable|string|max:255',
                'valor_numerico' => 'nullable|numeric',
                'valor_texto' => 'nullable|string|max:255',
                'activo' => 'sometimes|boolean',
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(
            $this->getRelacionesMensajes(),
            [
                'codigo.required' => 'El campo codigo es requerido',
                'codigo.string' => 'El campo codigo debe ser texto',
                'codigo.max' => 'El campo codigo no puede superar 60 caracteres',
                'abreviatura.string' => 'El campo abreviatura debe ser texto',
                'abreviatura.max' => 'El campo abreviatura no puede superar 20 caracteres',
                'nombre.required' => 'El campo nombre es requerido',
                'nombre.string' => 'El campo nombre debe ser texto',
                'nombre.max' => 'El campo nombre no puede superar 150 caracteres',
                'descripcion.string' => 'El campo descripcion debe ser texto',
                'descripcion.max' => 'El campo descripcion no puede superar 255 caracteres',
                'valor_numerico.numeric' => 'El campo valor_numerico debe ser numérico',
                'valor_texto.string' => 'El campo valor_texto debe ser texto',
                'valor_texto.max' => 'El campo valor_texto no puede superar 255 caracteres',
                'activo.boolean' => 'El campo activo debe ser verdadero o falso',
            ]
        );
    }
}
