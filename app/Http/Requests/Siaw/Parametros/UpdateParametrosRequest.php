<?php

namespace App\Http\Requests\Siaw\Parametros;

use App\Http\Requests\Siaw\Traits\SiawParametrosRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateParametrosRequest extends FormRequest
{
    use SiawParametrosRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $actual = $this->route('siaw_parametros');

        return array_merge(
            $this->getRelacionesRules('sometimes'),
            [
                'codigo' => ['sometimes', 'string', 'max:80', $this->uniqueCodigoRule($actual?->id, $actual?->sistema_id)],
                'descripcion' => "sometimes|string|max:255",
                'valor' => "nullable|string|max:255",
                'activo' => "sometimes|boolean",
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(
            $this->getRelacionesMensajes(),
            [
                'codigo.string' => 'El campo codigo debe ser texto',
                'codigo.max' => 'El campo codigo no puede superar 80 caracteres',
                'codigo.unique' => 'Ya existe un parámetro con ese codigo para el sistema indicado',
                'descripcion.string' => 'El campo descripcion debe ser texto',
                'descripcion.max' => 'El campo descripcion no puede superar 255 caracteres',
                'valor.string' => 'El campo valor debe ser texto',
                'valor.max' => 'El campo valor no puede superar 255 caracteres',
                'activo.boolean' => 'El campo activo debe ser verdadero o falso',
            ]
        );
    }
}
