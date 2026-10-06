<?php

namespace App\Http\Requests\Siaw\Usuarios;

use App\Http\Requests\Siaw\Traits\SiawUsuariosRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuariosRequest extends FormRequest
{
    use SiawUsuariosRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // "sometimes" en todos los campos — el update permite enviar solo
        // los que cambian. El password nunca se acepta aquí (ver
        // AuthController::cambiarPassword / resetPassword).
        $modelId = $this->route('siaw_usuario')?->id;

        return array_merge(
            $this->getRelacionesRules('sometimes'),
            [
                'nombre' => 'sometimes|string|max:100',
                'apellidos' => 'sometimes|nullable|string|max:100',
                'email' => ['sometimes', 'string', 'max:150', Rule::unique('dbsiaw.siaw_usuarios', 'email')->withoutTrashed()->ignore($modelId, 'id')],
                'activo' => 'sometimes|boolean',
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(
            $this->getRelacionesMensajes(),
            [
                'nombre.string' => 'El campo nombre debe ser texto',
                'nombre.max' => 'El campo nombre no puede superar 100 caracteres',
                'apellidos.string' => 'El campo apellidos debe ser texto',
                'apellidos.max' => 'El campo apellidos no puede superar 100 caracteres',
                'email.string' => 'El campo email debe ser texto',
                'email.max' => 'El campo email no puede superar 150 caracteres',
                'email.unique' => 'Ya existe un usuario con ese email.',
                'activo.boolean' => 'El campo activo debe ser verdadero o falso',
            ]
        );
    }
}
