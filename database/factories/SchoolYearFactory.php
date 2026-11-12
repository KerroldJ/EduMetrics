<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SchoolYear> */
class SchoolYearFactory extends Factory
{
    /** @return array{name: string} */
    public function definition(): array
    {
        $start = fake()->unique()->numberBetween(2000, 2098);

        return ['name' => $start.'-'.($start + 1)];
    }
}
