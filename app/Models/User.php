<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
 * @property bool $is_approved
 * @property int|null $strand_id
 * @property string|null $school_year
 * @property int|null $grade_level
 * @property string|null $section
 * @property string|null $school_id
 * @property string|null $mobile
 * @property string|null $avatar_path
 * @property-read string|null $avatar
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'avatar_path'])]
class User extends Authenticatable implements MustVerifyEmail
{
    protected $attributes = ['is_active' => true, 'is_approved' => true];

    protected $appends = ['avatar'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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
            'is_approved' => 'boolean',
            'grade_level' => 'integer',
        ];
    }

    /** @return BelongsTo<Strand, $this> */
    public function strand(): BelongsTo
    {
        return $this->belongsTo(Strand::class);
    }

    /** @return Attribute<string|null, never> */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null);
    }

    /** @return Builder<TeacherAssignment> */
    public function eligibleAssignments(?EvaluationPeriod $period = null): Builder
    {
        $schoolYear = $period ? $period->school_year : $this->school_year;
        $query = TeacherAssignment::query()->where('strand_id', $this->strand_id)
            ->where('school_year', $schoolYear)->where('is_active', true)
            ->whereHas('strand', fn (Builder $query): Builder => $query->where('is_active', true))
            ->whereHas('teacher', fn (Builder $query): Builder => $query->where('is_active', true)->whereHas('roles', fn (Builder $query): Builder => $query->where('name', 'Teacher')->where('guard_name', 'web')))
            ->where(fn (Builder $query): Builder => $query->whereNull('grade_level')->orWhere('grade_level', $this->grade_level))
            ->where(fn (Builder $query): Builder => $query->whereNull('section')->orWhere('section', $this->section));

        if (! $this->is_active || ! $this->strand_id || ! $schoolYear || $this->school_year !== $schoolYear) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
