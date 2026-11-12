<?php

namespace Database\Factories;

use App\Models\EvaluationCriterion;
use App\Models\EvaluationForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvaluationCriterion> */
class EvaluationCriterionFactory extends Factory
{
    public function definition(): array
    {
        return ['evaluation_form_id' => EvaluationForm::factory(), 'prompt' => fake()->sentence(), 'weight' => 1, 'position' => 1];
    }
}
