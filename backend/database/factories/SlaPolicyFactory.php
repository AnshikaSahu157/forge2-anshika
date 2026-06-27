<?php

namespace Database\Factories;

use App\Models\SlaPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'response_hours' => fake()->numberBetween(1, 24),
            'resolution_hours' => fake()->numberBetween(24, 168),
        ];
    }
}
