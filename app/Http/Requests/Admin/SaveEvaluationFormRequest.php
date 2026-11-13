<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveEvaluationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('forms.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['source_form_id' => ['nullable', 'integer', 'exists:evaluation_forms,id'], 'name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000'], 'scale_min' => ['required', 'integer', 'min:1', 'max:9'], 'scale_max' => ['required', 'integer', 'gt:scale_min', 'max:10'], 'is_active' => ['sometimes', 'boolean'], 'criteria' => ['required', 'array', 'min:1', 'max:100'], 'criteria.*.prompt' => ['required', 'string', 'max:1000'], 'criteria.*.weight' => ['required', 'numeric', 'min:0.01', 'max:1000']];
    }
}
