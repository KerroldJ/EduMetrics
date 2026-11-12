<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SchoolYear implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(20[0-9]{2})-(20[0-9]{2})$/', $value, $years) || (int) $years[2] !== (int) $years[1] + 1) {
            $fail('Use consecutive school years, for example 2026-2027.');
        }
    }
}
