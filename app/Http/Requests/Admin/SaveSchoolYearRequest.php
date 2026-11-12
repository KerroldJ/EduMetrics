<?php

namespace App\Http\Requests\Admin;

use App\Rules\SchoolYear as SchoolYearFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSchoolYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('strands.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', new SchoolYearFormat, Rule::unique('school_years')->ignore($this->route('school_year'))]];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }
}
