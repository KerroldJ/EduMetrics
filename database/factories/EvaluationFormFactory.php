<?php

namespace Database\Factories;

use App\Models\EvaluationForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EvaluationForm> */
class EvaluationFormFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->sentence(3), 'scale_min' => 1, 'scale_max' => 5, 'performance_bands' => [], 'is_active' => true];
    }
}
