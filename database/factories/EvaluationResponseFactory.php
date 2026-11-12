<?php

namespace Database\Factories;

use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvaluationResponse> */
class EvaluationResponseFactory extends Factory
{
    public function definition(): array
    {
        return ['evaluation_id' => Evaluation::factory(), 'evaluation_criterion_id' => EvaluationCriterion::factory(), 'rating' => 3];
    }
}
