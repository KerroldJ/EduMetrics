<?php

namespace App\Http\Requests\Admin;

use App\Rules\SchoolYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTeacherAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assignments.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['teacher_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)], 'strand_id' => ['required', Rule::exists('strands', 'id')->where('is_active', true)], 'school_year' => ['required', new SchoolYear, Rule::exists('school_years', 'name')], 'grade_level' => ['nullable', 'integer', Rule::in([11, 12])], 'section' => ['nullable', 'string', 'max:80', Rule::exists('sections', 'name')], 'is_active' => ['required', 'boolean']];
    }
}
