<?php

namespace Database\Factories;

use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Section> */
class SectionFactory extends Factory
{
    /** @return array{name: string} */
    public function definition(): array
    {
        return ['name' => fake()->unique()->bothify('Section ???###')];
    }
}
