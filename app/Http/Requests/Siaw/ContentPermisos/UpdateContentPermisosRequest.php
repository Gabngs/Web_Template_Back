<?php

namespace App\Http\Requests\Siaw\ContentPermisos;

use App\Http\Requests\Siaw\Traits\SiawContentPermisosRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentPermisosRequest extends FormRequest
{
    use SiawContentPermisosRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $modelId = $this->route('siaw_content_permiso')?->id;

        return array_merge(
            $this->getRelacionesRules('sometimes'),
            [
                'codename' => ['sometimes', 'string', 'max:100', Rule::unique('dbsiaw.siaw_content_permisos', 'codename')->withoutTrashed()->ignore($modelId, 'id')],
                'desc' => 'sometimes|string|max:255',
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(
            $this->getRelacionesMensajes(),
            [
                'codename.string' => 'El campo codename debe ser texto',
                'codename.max' => 'El campo codename no puede superar 100 caracteres',
                'codename.unique' => 'El codename ya existe.',
                'desc.string' => 'El campo desc debe ser texto',
                'desc.max' => 'El campo desc no puede superar 255 caracteres',
            ]
        );
    }
}
