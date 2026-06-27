<?php

use App\Models\Organization;
use App\Models\Notification;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);

    $this->admin = User::create([
        'organization_id' => $this->org->id,
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'role' => 'admin',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);

    $this->agent = User::create([
        'organization_id' => $this->org->id,
        'name' => 'Agent',
        'email' => 'agent@test.com',
        'role' => 'agent',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);

    $this->agent2 = User::create([
        'organization_id' => $this->org->id,
        'name' => 'Agent Two',
        'email' => 'agent2@test.com',
        'role' => 'agent',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);

    $this->customer = User::create([
        'organization_id' => $this->org->id,
        'name' => 'Customer',
        'email' => 'customer@test.com',
        'role' => 'customer',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);

    // SLA policies
    foreach (['low' => 168, 'medium' => 72, 'high' => 48, 'urgent' => 24] as $priority => $hours) {
        SlaPolicy::create([
            'organization_id' => $this->org->id,
            'priority' => $priority,
            'response_hours' => intval($hours / 4),
            'resolution_hours' => $hours,
        ]);
    }

    app(TenantContext::class)->setOrganizationId($this->org->id);
});

function getToken($user): string
{
    return $user->createToken('test')->plainTextToken;
}

describe('SLA Status', function () {
    it('computes sla_status for a ticket with a policy', function () {
        $ticket = Ticket::create([
            'organization_id' => test()->org->id,
            'subject' => 'SLA test',
            'description' => 'Testing SLA',
            'status' => 'open',
            'priority' => 'high',
            'requester_id' => test()->customer->id,
        ]);

        $sla = $ticket->sla_status;

        expect($sla['has_policy'])->toBeTrue()
            ->and($sla['is_breached'])->toBeFalse()
            ->and($sla['status'])->toBe('ok')
            ->and($sla['time_remaining'])->toBeGreaterThan(0);
    });

    it('detects breached ticket', function () {
        $ticket = new Ticket();
        $ticket->organization_id = test()->org->id;
        $ticket->subject = 'Old ticket';
        $ticket->description = 'Breached';
        $ticket->status = 'open';
        $ticket->priority = 'urgent'; // 24h resolution
        $ticket->requester_id = test()->customer->id;
        $ticket->created_at = now()->subHours(30);
        $ticket->saveQuietly();

        $sla = $ticket->sla_status;

        expect($sla['is_breached'])->toBeTrue()
            ->and($sla['status'])->toBe('breached');
    });

    it('shows warning when less than 20% time remaining', function () {
        $ticket = new Ticket();
        $ticket->organization_id = test()->org->id;
        $ticket->subject = 'Warning ticket';
        $ticket->description = 'Almost breached';
        $ticket->status = 'open';
        $ticket->priority = 'medium'; // 72h resolution
        $ticket->requester_id = test()->customer->id;
        // 60 hours old = ~83% elapsed = ~17% remaining
        $ticket->created_at = now()->subHours(60);
        $ticket->saveQuietly();

        $sla = $ticket->sla_status;

        expect($sla['status'])->toBe('warning')
            ->and($sla['percent_remaining'])->toBeLessThan(20.0);
    });

    it('returns none when no sla policy exists for priority', function () {
        $ticket = new Ticket();
        $ticket->organization_id = test()->org->id;
        $ticket->subject = 'No policy';
        $ticket->description = 'No SLA';
        $ticket->status = 'open';
        $ticket->priority = 'invalid_priority';
        $ticket->requester_id = test()->customer->id;
        $ticket->saveQuietly();

        $sla = $ticket->sla_status;

        expect($sla['has_policy'])->toBeFalse()
            ->and($sla['status'])->toBe('none');
    });

    it('includes sla_status in api response', function () {
        $token = getToken(test()->admin);

        $ticket = Ticket::create([
            'organization_id' => test()->org->id,
            'subject' => 'API SLA',
            'description' => 'API test',
            'status' => 'open',
            'priority' => 'high',
            'requester_id' => test()->customer->id,
        ]);

        $response = test()->withToken($token)->getJson("/api/tickets/{$ticket->id}");

        $response->assertOk()
            ->assertJsonStructure(['sla_status' => ['has_policy', 'is_breached', 'status', 'time_remaining']]);
    });
});

describe('Claim Ticket', function () {
    it('agent can claim an unassigned ticket', function () {
        $token = getToken(test()->agent);

        $ticket = Ticket::create([
            'organization_id' => test()->org->id,
            'subject' => 'Claim me',
            'description' => 'Unassigned',
            'status' => 'open',
            'priority' => 'medium',
            'requester_id' => test()->customer->id,
        ]);

        $response = test()->withToken($token)
            ->postJson("/api/tickets/{$ticket->id}/claim");

        $response->assertOk()
            ->assertJsonPath('assignee_id', test()->agent->id);
    });

    it('cannot claim an already assigned ticket', function () {
        $token = getToken(test()->agent);

        $ticket = Ticket::create([
            'organization_id' => test()->org->id,
            'subject' => 'Already assigned',
            'description' => 'Taken',
            'status' => 'open',
            'priority' => 'medium',
            'requester_id' => test()->customer->id,
            'assignee_id' => test()->agent2->id,
        ]);

        test()->withToken($token)
            ->postJson("/api/tickets/{$ticket->id}/claim")
            ->assertStatus(422);
    });
});

describe('Assign Ticket', function () {
    it('admin can assign a ticket to an agent', function () {
        $token = getToken(test()->admin);

        $ticket = Ticket::create([
            'organization_id' => test()->org->id,
            'subject' => 'Assign me',
            'description' => 'Need agent',
            'status' => 'open',
            'priority' => 'medium',
            'requester_id' => test()->customer->id,
        ]);

        $response = test()->withToken($token)
            ->postJson("/api/tickets/{$ticket->id}/assign", [
                'assignee_id' => test()->agent->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('assignee_id', test()->agent->id);
    });

    it('creates notification when ticket is assigned', function () {
        $token = getToken(test()->admin);

        $ticket = Ticket::create([
            'organization_id' => test()->org->id,
            'subject' => 'Notify assign',
            'description' => 'Should notify',
            'status' => 'open',
            'priority' => 'medium',
            'requester_id' => test()->customer->id,
        ]);

        test()->withToken($token)
            ->postJson("/api/tickets/{$ticket->id}/assign", [
                'assignee_id' => test()->agent->id,
            ]);

        $notif = Notification::where('user_id', test()->agent->id)
            ->where('type', 'ticket_assigned')
            ->first();

        expect($notif)->not->toBeNull()
            ->and($notif->data['ticket_id'])->toBe($ticket->id);
    });

    it('cannot assign to user from another organization', function () {
        $token = getToken(test()->admin);

        $org2 = Organization::create(['name' => 'Other Org', 'slug' => 'other-org']);
        $org2User = User::create([
            'organization_id' => $org2->id,
            'name' => 'Other',
            'email' => 'other@org.com',
            'role' => 'agent',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $ticket = Ticket::create([
            'organization_id' => test()->org->id,
            'subject' => 'Cross org',
            'description' => 'No',
            'status' => 'open',
            'priority' => 'medium',
            'requester_id' => test()->customer->id,
        ]);

        test()->withToken($token)
            ->postJson("/api/tickets/{$ticket->id}/assign", [
                'assignee_id' => $org2User->id,
            ])
            ->assertStatus(422);
    });
});

describe('Notifications', function () {
    it('can list notifications for auth user', function () {
        $token = getToken(test()->agent);

        Notification::create([
            'organization_id' => test()->org->id,
            'user_id' => test()->agent->id,
            'type' => 'ticket_assigned',
            'data' => ['ticket_id' => 1],
        ]);

        Notification::create([
            'organization_id' => test()->org->id,
            'user_id' => test()->agent->id,
            'type' => 'comment_added',
            'data' => ['ticket_id' => 2],
        ]);

        $response = test()->withToken($token)->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'data');
    });

    it('can mark a notification as read', function () {
        $token = getToken(test()->agent);

        $notif = Notification::create([
            'organization_id' => test()->org->id,
            'user_id' => test()->agent->id,
            'type' => 'ticket_assigned',
            'data' => ['ticket_id' => 1],
        ]);

        test()->withToken($token)
            ->postJson("/api/notifications/{$notif->id}/read")
            ->assertOk();

        expect($notif->fresh()->read_at)->not->toBeNull();
    });

    it('fires notification when comment is added to assigned ticket', function () {
        $token = getToken(test()->customer);

        $ticket = Ticket::create([
            'organization_id' => test()->org->id,
            'subject' => 'Comment notify',
            'description' => 'Notify assignee',
            'status' => 'open',
            'priority' => 'medium',
            'requester_id' => test()->customer->id,
            'assignee_id' => test()->agent->id,
        ]);

        test()->withToken($token)
            ->postJson("/api/tickets/{$ticket->id}/comments", [
                'body' => 'Please help!',
            ]);

        $notif = Notification::where('user_id', test()->agent->id)
            ->where('type', 'comment_added')
            ->first();

        expect($notif)->not->toBeNull()
            ->and($notif->data['ticket_id'])->toBe($ticket->id);
    });

    it('only shows own notifications', function () {
        $token = getToken(test()->agent);

        Notification::create([
            'organization_id' => test()->org->id,
            'user_id' => test()->agent2->id,
            'type' => 'ticket_assigned',
            'data' => ['ticket_id' => 1],
        ]);

        Notification::create([
            'organization_id' => test()->org->id,
            'user_id' => test()->agent->id,
            'type' => 'comment_added',
            'data' => ['ticket_id' => 2],
        ]);

        $response = test()->withToken($token)->getJson('/api/notifications');

        expect(collect($response->json('data')))->toHaveCount(1)
            ->and($response->json('data.0.user_id'))->toBe(test()->agent->id);
    });
});
