<?php

namespace Database\Factories;

use App\Models\Dataset;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dataset>
 */
class DatasetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(3, true),
            'status' => 'ready',
            'rows' => fake()->numberBetween(100, 50000),
        ];
    }
}
