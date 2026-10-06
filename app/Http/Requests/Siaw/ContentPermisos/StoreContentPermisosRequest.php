<?php

namespace App\Http\Requests\Siaw\ContentPermisos;

use App\Http\Requests\Siaw\Traits\SiawContentPermisosRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentPermisosRequest extends FormRequest
{
    use SiawContentPermisosRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            $this->getRelacionesRules('required'),
            [
                'codename' => ['required', 'string', 'max:100', Rule::unique('dbsiaw.siaw_content_permisos', 'codename')->withoutTrashed()],
                'desc' => 'required|string|max:255',
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(
            $this->getRelacionesMensajes(),
            [
                'codename.required' => 'El codename es requerido.',
                'codename.string' => 'El campo codename debe ser texto',
                'codename.max' => 'El campo codename no puede superar 100 caracteres',
                'codename.unique' => 'El codename ya existe.',
                'desc.required' => 'La descripción es requerida.',
                'desc.string' => 'El campo desc debe ser texto',
                'desc.max' => 'El campo desc no puede superar 255 caracteres',
            ]
        );
    }
}
