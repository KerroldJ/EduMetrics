<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveStrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('strands.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:160'], 'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('strands')->ignore($this->route('strand'))], 'is_active' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        if (is_string($code)) {
            $this->merge(['code' => Str::upper(trim($code))]);
        }
    }
}
