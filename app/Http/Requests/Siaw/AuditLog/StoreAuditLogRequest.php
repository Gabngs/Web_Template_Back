<?php

namespace App\Http\Requests\Siaw\AuditLog;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_proceso' => 'required|string|max:255',
            'tipo_proceso' => 'required|integer|between:1,4',
            'estado_proceso' => 'required|integer|between:1,2',
            'origen' => 'required|integer|between:1,2',
            'modelo_afectado' => 'nullable|string|max:255',
            'registro_id' => 'nullable|string|max:255',
            'input' => 'nullable|array',
            'output' => 'nullable|array',
            'error' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_proceso.required' => 'El campo nombre_proceso es requerido',
            'nombre_proceso.string' => 'El campo nombre_proceso debe ser texto',
            'nombre_proceso.max' => 'El campo nombre_proceso no puede superar 255 caracteres',
            'tipo_proceso.required' => 'El campo tipo_proceso es requerido',
            'tipo_proceso.integer' => 'El campo tipo_proceso debe ser un número entero',
            'tipo_proceso.between' => 'El campo tipo_proceso debe estar entre 1 y 4',
            'estado_proceso.required' => 'El campo estado_proceso es requerido',
            'estado_proceso.integer' => 'El campo estado_proceso debe ser un número entero',
            'estado_proceso.between' => 'El campo estado_proceso debe estar entre 1 y 2',
            'origen.required' => 'El campo origen es requerido',
            'origen.integer' => 'El campo origen debe ser un número entero',
            'origen.between' => 'El campo origen debe estar entre 1 y 2',
            'modelo_afectado.string' => 'El campo modelo_afectado debe ser texto',
            'modelo_afectado.max' => 'El campo modelo_afectado no puede superar 255 caracteres',
            'registro_id.string' => 'El campo registro_id debe ser texto',
            'registro_id.max' => 'El campo registro_id no puede superar 255 caracteres',
            'input.array' => 'El campo input debe ser un objeto o arreglo JSON',
            'output.array' => 'El campo output debe ser un objeto o arreglo JSON',
            'error.string' => 'El campo error debe ser texto',
        ];
    }
}
