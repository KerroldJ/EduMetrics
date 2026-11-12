<?php

namespace Database\Factories;

use App\Models\Strand;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeacherAssignment> */
class TeacherAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return ['teacher_id' => User::factory(), 'strand_id' => Strand::factory(), 'school_year' => '2026-2027', 'is_active' => true];
    }
}
