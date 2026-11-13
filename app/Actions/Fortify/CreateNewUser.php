<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower(trim($input['email']));
        }
        $data = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::in(['Teacher', 'Student'])],
            'strand_id' => ['required', 'integer', Rule::exists('strands', 'id')->where('is_active', true)],
            'section' => ['required', 'string', 'max:80', Rule::exists('sections', 'name')],
            'grade_level' => ['exclude_unless:role,Student', 'required', 'integer', Rule::in([11, 12])],
        ])->validate();

        return DB::transaction(function () use ($data): User {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'strand_id' => $data['strand_id'],
                'school_year' => SchoolYear::orderByDesc('name')->value('name'),
                'section' => $data['section'],
                'grade_level' => $data['grade_level'] ?? null,
                'is_approved' => false,
            ])->save();
            $user->assignRole($data['role']);

            return $user;
        });
    }
}
