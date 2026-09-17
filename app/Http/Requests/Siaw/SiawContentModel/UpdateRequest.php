<?php

namespace App\Http\Requests\Siaw\SiawContentModel;

use App\Http\Requests\Siaw\Traits\SiawContentModelRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    use SiawContentModelRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_map(
            fn($rules) => array_merge(['sometimes'], $rules),
            $this->fieldRules($this->route('siaw_content_model')?->id)
        );
    }
}
