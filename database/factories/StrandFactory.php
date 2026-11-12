<?php

namespace Database\Factories;

use App\Models\Strand;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Strand> */
class StrandFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('???###')), 'name' => fake()->words(3, true), 'is_active' => true];
    }
}
