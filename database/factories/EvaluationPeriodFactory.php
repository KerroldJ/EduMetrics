<?php

namespace Database\Factories;

use App\Models\EvaluationForm;
use App\Models\EvaluationPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvaluationPeriod> */
class EvaluationPeriodFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->sentence(3), 'school_year' => '2026-2027', 'evaluation_form_id' => EvaluationForm::factory(), 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'status' => 'draft', 'minimum_responses' => 3];
    }
}
