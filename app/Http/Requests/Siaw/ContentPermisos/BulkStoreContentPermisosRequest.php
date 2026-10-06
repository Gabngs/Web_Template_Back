<?php

namespace App\Http\Requests\Siaw\ContentPermisos;

use App\Http\Requests\Siaw\Traits\SiawContentPermisosRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreContentPermisosRequest extends FormRequest
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
                'permisos' => 'required|array|min:1',
                'permisos.*.codename' => ['required', 'string', 'max:100', 'distinct', Rule::unique('dbsiaw.siaw_content_permisos', 'codename')->withoutTrashed()],
                'permisos.*.desc' => 'required|string|max:255',
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(
            $this->getRelacionesMensajes(),
            [
                'permisos.required' => 'El campo permisos es requerido',
                'permisos.array' => 'El campo permisos debe ser una lista',
                'permisos.min' => 'Debe enviar al menos un permiso',
                'permisos.*.codename.required' => 'El codename es requerido.',
                'permisos.*.codename.string' => 'El campo codename debe ser texto',
                'permisos.*.codename.max' => 'El campo codename no puede superar 100 caracteres',
                'permisos.*.codename.distinct' => 'El codename está repetido en la lista.',
                'permisos.*.codename.unique' => 'El codename ya existe.',
                'permisos.*.desc.required' => 'La descripción es requerida.',
                'permisos.*.desc.string' => 'El campo desc debe ser texto',
                'permisos.*.desc.max' => 'El campo desc no puede superar 255 caracteres',
            ]
        );
    }
}
