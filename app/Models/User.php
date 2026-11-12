<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $is_active
 * @property int|null $strand_id
 * @property string|null $school_year
 * @property int|null $grade_level
 * @property string|null $section
 * @property string|null $school_id
 * @property string|null $mobile
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    protected $attributes = ['is_active' => true];

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'grade_level' => 'integer',
        ];
    }

    /** @return BelongsTo<Strand, $this> */
    public function strand(): BelongsTo
    {
        return $this->belongsTo(Strand::class);
    }

    /** @return Builder<TeacherAssignment> */
    public function eligibleAssignments(EvaluationPeriod $period): Builder
    {
        $query = TeacherAssignment::query()->where('strand_id', $this->strand_id)
            ->where('school_year', $period->school_year)->where('is_active', true)
            ->whereHas('strand', fn (Builder $query): Builder => $query->where('is_active', true))
            ->whereHas('teacher', fn (Builder $query): Builder => $query->where('is_active', true)->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Teacher')->where('guard_name', 'web')))
            ->where(fn (Builder $query): Builder => $query->whereNull('grade_level')->orWhere('grade_level', $this->grade_level))
            ->where(fn (Builder $query): Builder => $query->whereNull('section')->orWhere('section', $this->section));

        if (! $this->is_active || ! $this->strand_id || $this->school_year !== $period->school_year) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
