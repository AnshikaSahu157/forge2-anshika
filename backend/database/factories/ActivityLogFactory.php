<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        $actions = ['created', 'updated', 'assigned', 'status_changed', 'commented', 'priority_changed'];

        return [
            'action' => fake()->randomElement($actions),
            'properties' => null,
        ];
    }
}
