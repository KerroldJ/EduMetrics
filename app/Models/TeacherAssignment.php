<?php

namespace App\Models;

use Database\Factories\TeacherAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['teacher_id', 'strand_id', 'school_year', 'grade_level', 'section', 'is_active'])]
class TeacherAssignment extends Model
{
    /** @use HasFactory<TeacherAssignmentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'grade_level' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /** @return BelongsTo<Strand, $this> */
    public function strand(): BelongsTo
    {
        return $this->belongsTo(Strand::class, 'strand_id');
    }
}
