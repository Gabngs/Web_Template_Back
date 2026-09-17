<?php

namespace App\Http\Requests\Siaw\Traits;

use Illuminate\Validation\Rule;

trait SiawRolesRules
{
    protected function fieldRules(?string $ignoreId = null): array
    {
        $uniqueName = Rule::unique('dbsiaw.siaw_roles', 'name')->withoutTrashed();
        $uniqueSlug = Rule::unique('dbsiaw.siaw_roles', 'slug')->withoutTrashed();

        if ($ignoreId) {
            $uniqueName = $uniqueName->ignore($ignoreId, 'id');
            $uniqueSlug = $uniqueSlug->ignore($ignoreId, 'id');
        }

        return [
            'name'        => ['string', 'max:100', $uniqueName],
            'slug'        => ['string', 'max:100', $uniqueSlug],
            'guard_name'  => ['string', 'max:50'],
            'descripcion' => ['string', 'max:255'],
            'activo'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del rol es requerido.',
            'name.unique'   => 'El nombre del rol ya existe.',
            'slug.required' => 'El slug es requerido.',
            'slug.unique'   => 'El slug ya existe.',
        ];
    }
}
