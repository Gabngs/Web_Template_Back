<?php

namespace App\Http\Requests\Siaw\Parametros;

use App\Http\Requests\Siaw\Traits\SiawParametrosRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreParametrosRequest extends FormRequest
{
    use SiawParametrosRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->getRelacionesRules('required'),
            [
                'codigo' => 'required|string|max:80',
                'descripcion' => 'required|string|max:255',
                'valor' => 'nullable|string|max:255',
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
                'codigo.max' => 'El campo codigo no puede superar 80 caracteres',
                'descripcion.required' => 'El campo descripcion es requerido',
                'descripcion.string' => 'El campo descripcion debe ser texto',
                'descripcion.max' => 'El campo descripcion no puede superar 255 caracteres',
                'valor.string' => 'El campo valor debe ser texto',
                'valor.max' => 'El campo valor no puede superar 255 caracteres',
                'activo.boolean' => 'El campo activo debe ser verdadero o falso',
            ]
        );
    }
}
