<?php

namespace App\Models;

use Database\Factories\SchoolYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class SchoolYear extends Model
{
    /** @use HasFactory<SchoolYearFactory> */
    use HasFactory;

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'school_year', 'name');
    }

    /** @return HasMany<TeacherAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'school_year', 'name');
    }

    /** @return HasMany<EvaluationPeriod, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(EvaluationPeriod::class, 'school_year', 'name');
    }
}
