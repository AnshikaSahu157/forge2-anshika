<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        $statuses = ['open', 'in_progress', 'resolved', 'closed'];
        $priorities = ['low', 'medium', 'high', 'urgent'];

        return [
            'organization_id' => Organization::factory(),
            'subject' => fake()->sentence(4, 8),
            'description' => fake()->paragraphs(2, true),
            'status' => fake()->randomElement($statuses),
            'priority' => fake()->randomElement($priorities),
            'requester_id' => User::factory(),
            'assignee_id' => null,
            'tags' => fake()->randomElements(['bug', 'feature', 'urgent', 'billing', 'technical', 'question'], fake()->numberBetween(0, 3)),
        ];
    }
}
