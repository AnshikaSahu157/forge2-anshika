<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // --- Organization ---
        $org = Organization::create([
            'name' => 'Acme Support Corp',
            'slug' => 'acme-support',
        ]);

        // --- Second org for cross-tenant tests ---
        $org2 = Organization::create([
            'name' => 'Beta Industries',
            'slug' => 'beta-industries',
        ]);

        // --- Users for Org 1 ---
        $admin = User::create([
            'organization_id' => $org->id,
            'name' => 'Alice Admin',
            'email' => 'admin@acme.test',
            'role' => 'admin',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        $agent1 = User::create([
            'organization_id' => $org->id,
            'name' => 'Arnold Agent',
            'email' => 'arnold@acme.test',
            'role' => 'agent',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        $agent2 = User::create([
            'organization_id' => $org->id,
            'name' => 'Anna Agent',
            'email' => 'anna@acme.test',
            'role' => 'agent',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        $customer1 = User::create([
            'organization_id' => $org->id,
            'name' => 'Charlie Customer',
            'email' => 'charlie@acme.test',
            'role' => 'customer',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        $customer2 = User::create([
            'organization_id' => $org->id,
            'name' => 'Clara Customer',
            'email' => 'clara@acme.test',
            'role' => 'customer',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        // --- User for Org 2 (cross-tenant test) ---
        $org2User = User::create([
            'organization_id' => $org2->id,
            'name' => 'Brenda Beta',
            'email' => 'brenda@beta.test',
            'role' => 'admin',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        // --- SLA Policies ---
        foreach (['low', 'medium', 'high', 'urgent'] as $priority) {
            SlaPolicy::create([
                'organization_id' => $org->id,
                'priority' => $priority,
                'response_hours' => match ($priority) {
                    'urgent' => 1,
                    'high' => 4,
                    'medium' => 8,
                    'low' => 24,
                },
                'resolution_hours' => match ($priority) {
                    'urgent' => 24,
                    'high' => 48,
                    'medium' => 72,
                    'low' => 168,
                },
            ]);
        }

        // --- 12 Tickets with varied statuses/priorities ---
        $statuses = ['open', 'open', 'in_progress', 'in_progress', 'resolved', 'resolved', 'closed', 'closed', 'open', 'in_progress', 'resolved', 'closed'];
        $priorities = ['urgent', 'high', 'high', 'medium', 'medium', 'low', 'low', 'high', 'urgent', 'medium', 'low', 'high'];
        $requesters = [$customer1, $customer2, $customer1, $customer2, $customer1, $customer2, $customer1, $customer2, $customer1, $customer2, $customer1, $customer2];
        $assignees = [$agent1, $agent1, $agent2, $agent2, $agent1, null, $agent2, $agent1, $agent2, $agent2, $agent1, null];
        $tagsPool = [['bug', 'urgent'], ['feature'], ['billing'], ['technical', 'bug'], ['question'], [], ['urgent', 'escalated'], ['bug'], ['feature', 'planning'], ['technical'], ['billing', 'refund'], ['question']];

        $subjects = [
            'Login page returns 500 error',
            'Feature request: Dark mode',
            'Invoice shows wrong amount',
            'API rate limiting too aggressive',
            'Cannot reset password',
            'Export to CSV missing columns',
            'Email notifications not arriving',
            'Mobile app crashes on startup',
            'SSO integration with Google',
            'Slow response on dashboard load',
            'Refund request for order #4821',
            'How to add custom fields?',
        ];

        for ($i = 0; $i < 12; $i++) {
            $ticket = Ticket::create([
                'organization_id' => $org->id,
                'subject' => $subjects[$i],
                'description' => fake()->paragraphs(3, true),
                'status' => $statuses[$i],
                'priority' => $priorities[$i],
                'requester_id' => $requesters[$i]->id,
                'assignee_id' => $assignees[$i]?->id,
                'tags' => $tagsPool[$i],
            ]);

            ActivityLog::create([
                'organization_id' => $org->id,
                'ticket_id' => $ticket->id,
                'user_id' => $requesters[$i]->id,
                'action' => 'created',
                'properties' => ['status' => $ticket->status, 'priority' => $ticket->priority],
            ]);
        }

        // --- Ticket in Org 2 for cross-tenant isolation test ---
        $org2Ticket = Ticket::create([
            'organization_id' => $org2->id,
            'subject' => 'Beta org internal issue',
            'description' => 'This should not be visible to Acme users',
            'status' => 'open',
            'priority' => 'high',
            'requester_id' => $org2User->id,
            'assignee_id' => null,
            'tags' => ['internal'],
        ]);

        ActivityLog::create([
            'organization_id' => $org2->id,
            'ticket_id' => $org2Ticket->id,
            'user_id' => $org2User->id,
            'action' => 'created',
            'properties' => ['status' => 'open'],
        ]);
    }
}
