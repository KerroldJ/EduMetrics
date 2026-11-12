<?php

namespace App\Http\Requests\Admin;

use App\Rules\SchoolYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))], 'password' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'], 'role' => ['required', Rule::in(['Admin', 'Teacher', 'Student'])], 'is_active' => ['required', 'boolean'], 'email_verified' => ['sometimes', 'boolean'], 'strand_id' => ['nullable', 'exists:strands,id'], 'school_year' => ['nullable', new SchoolYear, Rule::exists('school_years', 'name')], 'grade_level' => ['nullable', 'integer', Rule::in([11, 12])], 'section' => ['nullable', 'string', 'max:80', Rule::exists('sections', 'name')], 'school_id' => ['nullable', 'string', 'max:80', Rule::unique('users')->ignore($this->route('user'))], 'mobile' => ['nullable', 'regex:/^\+639[0-9]{9}$/']];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }
}
