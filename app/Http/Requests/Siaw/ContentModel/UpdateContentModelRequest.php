<?php

namespace App\Http\Requests\Siaw\ContentModel;

use App\Http\Requests\Siaw\Traits\SiawContentModelRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentModelRequest extends FormRequest
{
    use SiawContentModelRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $modelId = $this->route('siaw_content_model')?->id;

        return array_merge(
            $this->getRelacionesRules('sometimes'),
            [
                'app_label' => 'sometimes|string|max:100',
                'app_model' => ['sometimes', 'string', 'max:100', Rule::unique('dbsiaw.siaw_content_model', 'app_model')->withoutTrashed()->ignore($modelId, 'id')],
                'nombre_display' => 'sometimes|string|max:255',
            ]
        );
    }

    public function messages(): array
    {
        return array_merge(
            $this->getRelacionesMensajes(),
            [
                'app_label.string' => 'El campo app_label debe ser texto',
                'app_label.max' => 'El campo app_label no puede superar 100 caracteres',
                'app_model.string' => 'El campo app_model debe ser texto',
                'app_model.max' => 'El campo app_model no puede superar 100 caracteres',
                'app_model.unique' => 'El modelo ya existe.',
                'nombre_display.string' => 'El campo nombre_display debe ser texto',
                'nombre_display.max' => 'El campo nombre_display no puede superar 255 caracteres',
            ]
        );
    }
}
