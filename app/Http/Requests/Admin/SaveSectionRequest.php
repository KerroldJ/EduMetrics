<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('strands.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80', Rule::unique('sections')->ignore($this->route('section'))]];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }
}
