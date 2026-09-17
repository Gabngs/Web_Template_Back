<?php

namespace App\Http\Requests\Siaw\SiawContentPermisos;

use App\Http\Requests\Siaw\Traits\SiawContentPermisosRules;
use Illuminate\Foundation\Http\FormRequest;


class BulkStoreRequest extends FormRequest
{
    use SiawContentPermisosRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content_model_id' => $this->fieldRules()['content_model_id'] + ['required'],
            'permisos' => ['required', 'array', 'min:1'],
            'permisos.*.codename' => $this->fieldRules()['codename'],
            'permisos.*.desc' => $this->fieldRules()['desc'],
        ];
    }
}
