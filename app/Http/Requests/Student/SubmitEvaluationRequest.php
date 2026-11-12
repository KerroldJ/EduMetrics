<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SubmitEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('evaluations.submit') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['assignment_id' => ['required', 'integer', 'exists:teacher_assignments,id'], 'ratings' => ['required', 'array', 'min:1', 'max:100'], 'ratings.*' => ['required', 'integer', 'min:1', 'max:10'], 'comment' => ['nullable', 'string', 'max:2000']];
    }

    /** @return array<array-key, int> */
    public function ratings(): array
    {
        return array_map(fn ($rating): int => (int) $rating, $this->validated('ratings'));
    }
}
