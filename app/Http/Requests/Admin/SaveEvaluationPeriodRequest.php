<?php

namespace App\Http\Requests\Admin;

use App\Rules\SchoolYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEvaluationPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('periods.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:160'], 'school_year' => ['required', new SchoolYear, Rule::exists('school_years', 'name')], 'evaluation_form_id' => ['required', 'integer', Rule::exists('evaluation_forms', 'id')], 'opens_at' => ['required', 'date'], 'closes_at' => ['required', 'date', 'after:opens_at'], 'minimum_responses' => ['required', 'integer', 'min:1', 'max:100']];
    }
}
