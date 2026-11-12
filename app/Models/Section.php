<?php

namespace App\Models;

use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use HasFactory;

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'section', 'name');
    }

    /** @return HasMany<TeacherAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'section', 'name');
    }
}
