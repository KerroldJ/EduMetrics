<?php

namespace Database\Factories;

use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Evaluation> */
class EvaluationFactory extends Factory
{
    public function definition(): array
    {
        return ['student_id' => User::factory(), 'teacher_id' => User::factory(), 'teacher_assignment_id' => TeacherAssignment::factory(), 'evaluation_period_id' => EvaluationPeriod::factory(), 'evaluation_form_id' => EvaluationForm::factory(), 'score' => 3, 'context' => [], 'submitted_at' => now()];
    }
}
