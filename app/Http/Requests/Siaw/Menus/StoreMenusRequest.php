<?php

namespace App\Http\Requests\Siaw\Menus;

use App\Http\Requests\Siaw\Traits\SiawMenusRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreMenusRequest extends FormRequest
{
    use SiawMenusRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->getRelacionesRules('required'),
            [
                'titulo' => 'required|string|max:100',
                'descripcion' => 'nullable|string|max:255',
                'ruta' => 'nullable|string|max:255',
                'nombre_icon' => 'nullable|string|max:100',
                'orden' => 'sometimes|integer|min:0',
                'activo' => 'sometimes|boolean',
                'dashboard' => 'sometimes|boolean',
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(
            $this->getRelacionesMensajes(),
            [
                'titulo.required' => 'El título es requerido.',
                'titulo.string' => 'El campo titulo debe ser texto',
                'titulo.max' => 'El campo titulo no puede superar 100 caracteres',
                'descripcion.string' => 'El campo descripcion debe ser texto',
                'descripcion.max' => 'El campo descripcion no puede superar 255 caracteres',
                'ruta.string' => 'El campo ruta debe ser texto',
                'ruta.max' => 'El campo ruta no puede superar 255 caracteres',
                'nombre_icon.string' => 'El campo nombre_icon debe ser texto',
                'nombre_icon.max' => 'El campo nombre_icon no puede superar 100 caracteres',
                'orden.integer' => 'El campo orden debe ser un número entero',
                'orden.min' => 'El campo orden no puede ser negativo',
                'activo.boolean' => 'El campo activo debe ser verdadero o falso',
                'dashboard.boolean' => 'El campo dashboard debe ser verdadero o falso',
            ]
        );
    }
}
