<?php

namespace App\Http\Requests\Siaw\Sistemas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSistemasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $modelId = $this->route('siaw_sistema')?->id;

        return [
            'codigo' => ['sometimes', 'string', 'max:20', Rule::unique('dbsiaw.siaw_sistemas', 'codigo')->withoutTrashed()->ignore($modelId, 'id')],
            'descripcion' => 'sometimes|string|max:100',
            'activo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.string' => 'El campo codigo debe ser texto',
            'codigo.max' => 'El campo codigo no puede superar 20 caracteres',
            'codigo.unique' => 'Ese código ya existe.',
            'descripcion.string' => 'El campo descripcion debe ser texto',
            'descripcion.max' => 'El campo descripcion no puede superar 100 caracteres',
            'activo.boolean' => 'El campo activo debe ser verdadero o falso',
        ];
    }
}
