<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->org1 = Organization::create(['name' => 'Org One', 'slug' => 'org-one']);
    $this->org2 = Organization::create(['name' => 'Org Two', 'slug' => 'org-two']);

    // Org1 users
    $this->admin1 = User::create([
        'organization_id' => $this->org1->id,
        'name' => 'Admin One',
        'email' => 'admin1@test.com',
        'role' => 'admin',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);

    $this->customer1 = User::create([
        'organization_id' => $this->org1->id,
        'name' => 'Customer One',
        'email' => 'cust1@test.com',
        'role' => 'customer',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);

    // Org2 users
    $this->admin2 = User::create([
        'organization_id' => $this->org2->id,
        'name' => 'Admin Two',
        'email' => 'admin2@test.com',
        'role' => 'admin',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);

    $this->customer2 = User::create([
        'organization_id' => $this->org2->id,
        'name' => 'Customer Two',
        'email' => 'cust2@test.com',
        'role' => 'customer',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
});

function loginAs($user): string
{
    return $user->createToken('test-token')->plainTextToken;
}

function createTicketForOrg($orgId, $requesterId, $attributes = []): Ticket
{
    return Ticket::create(array_merge([
        'organization_id' => $orgId,
        'subject' => 'Test Ticket',
        'description' => 'Test description',
        'status' => 'open',
        'priority' => 'medium',
        'requester_id' => $requesterId,
    ], $attributes));
}

describe('Ticket CRUD', function () {
    it('can list tickets', function () {
        $token = loginAs($this->admin1);
        app(TenantContext::class)->setOrganizationId($this->org1->id);

        createTicketForOrg($this->org1->id, $this->customer1->id, ['subject' => 'Ticket A']);
        createTicketForOrg($this->org1->id, $this->customer1->id, ['subject' => 'Ticket B']);

        $response = $this->withToken($token)->getJson('/api/tickets');

        $response->assertOk();
        $subjects = collect($response->json('data'))->pluck('subject');
        expect($subjects)->toContain('Ticket A', 'Ticket B')
            ->and($subjects)->toHaveCount(2);
    });

    it('can filter tickets by status', function () {
        $token = loginAs($this->admin1);
        app(TenantContext::class)->setOrganizationId($this->org1->id);

        createTicketForOrg($this->org1->id, $this->customer1->id, ['status' => 'open', 'subject' => 'Open One']);
        createTicketForOrg($this->org1->id, $this->customer1->id, ['status' => 'closed', 'subject' => 'Closed One']);

        $this->withToken($token)
            ->getJson('/api/tickets?status=open')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'Open One')
            ->assertJsonMissingPath('data.1.subject');
    });

    it('can filter tickets by priority', function () {
        $token = loginAs($this->admin1);
        app(TenantContext::class)->setOrganizationId($this->org1->id);

        createTicketForOrg($this->org1->id, $this->customer1->id, ['priority' => 'urgent', 'subject' => 'Urgent']);
        createTicketForOrg($this->org1->id, $this->customer1->id, ['priority' => 'low', 'subject' => 'Low']);

        $this->withToken($token)
            ->getJson('/api/tickets?priority=urgent')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'Urgent');
    });

    it('can search tickets by subject', function () {
        $token = loginAs($this->admin1);
        app(TenantContext::class)->setOrganizationId($this->org1->id);

        createTicketForOrg($this->org1->id, $this->customer1->id, ['subject' => 'Login page broken']);
        createTicketForOrg($this->org1->id, $this->customer1->id, ['subject' => 'Billing issue']);

        $this->withToken($token)
            ->getJson('/api/tickets?search=Login')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'Login page broken');
    });

    it('can search tickets by description', function () {
        $token = loginAs($this->admin1);
        app(TenantContext::class)->setOrganizationId($this->org1->id);

        createTicketForOrg($this->org1->id, $this->customer1->id, [
            'subject' => 'Generic',
            'description' => 'The payment gateway is down',
        ]);

        $this->withToken($token)
            ->getJson('/api/tickets?search=payment')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'Generic');
    });

    it('can create a ticket', function () {
        $token = loginAs($this->customer1);

        $response = $this->withToken($token)->postJson('/api/tickets', [
            'subject' => 'New API ticket',
            'description' => 'Created via API',
            'priority' => 'high',
        ]);

        $response->assertCreated()
            ->assertJsonPath('subject', 'New API ticket')
            ->assertJsonPath('priority', 'high');

        expect($response->json('status'))->toBe('open');

        expect($response->json('requester.id'))->toBe($this->customer1->id);
    });

    it('sets requester to authenticated user', function () {
        $token = loginAs($this->customer1);

        $response = $this->withToken($token)->postJson('/api/tickets', [
            'subject' => 'My ticket',
            'description' => 'I am the requester',
        ]);

        expect($response->json('requester.id'))->toBe($this->customer1->id);
    });

    it('validates required fields on create', function () {
        $token = loginAs($this->customer1);

        $this->withToken($token)
            ->postJson('/api/tickets', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'description']);
    });

    it('can show a ticket', function () {
        $token = loginAs($this->admin1);
        app(TenantContext::class)->setOrganizationId($this->org1->id);

        $ticket = createTicketForOrg($this->org1->id, $this->customer1->id, ['subject' => 'Show Me']);

        $this->withToken($token)
            ->getJson("/api/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('subject', 'Show Me');
    });

    it('can update a ticket', function () {
        $token = loginAs($this->admin1);
        app(TenantContext::class)->setOrganizationId($this->org1->id);

        $ticket = createTicketForOrg($this->org1->id, $this->customer1->id);

        $this->withToken($token)
            ->putJson("/api/tickets/{$ticket->id}", [
                'status' => 'in_progress',
                'priority' => 'urgent',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'in_progress')
            ->assertJsonPath('priority', 'urgent');
    });

    it('can delete a ticket as admin', function () {
        $token = loginAs($this->admin1);
        app(TenantContext::class)->setOrganizationId($this->org1->id);

        $ticket = createTicketForOrg($this->org1->id, $this->customer1->id);

        $this->withToken($token)
            ->deleteJson("/api/tickets/{$ticket->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
    });

    it('requires authentication', function () {
        $this->getJson('/api/tickets')->assertUnauthorized();
        $this->postJson('/api/tickets', [])->assertUnauthorized();
    });
});

describe('Cross-Tenant Isolation', function () {
    it('does not return tickets from another organization', function () {
        $token1 = loginAs($this->admin1);
        app(TenantContext::class)->setOrganizationId($this->org1->id);

        createTicketForOrg($this->org1->id, $this->customer1->id, ['subject' => 'Org1 Ticket']);

        // Create org2 ticket bypassing scope
        $org2Ticket = new Ticket();
        $org2Ticket->organization_id = $this->org2->id;
        $org2Ticket->subject = 'Org2 Secret';
        $org2Ticket->description = 'Hidden';
        $org2Ticket->status = 'open';
        $org2Ticket->priority = 'high';
        $org2Ticket->requester_id = $this->customer2->id;
        $org2Ticket->saveQuietly();

        $response = $this->withToken($token1)->getJson('/api/tickets');

        $response->assertOk();
        expect(collect($response->json('data'))->pluck('subject'))->not->toContain('Org2 Secret')
            ->and(collect($response->json('data'))->pluck('subject'))->toContain('Org1 Ticket');
    });

    it('returns 403 when accessing another org ticket by ID', function () {
        $token1 = loginAs($this->admin1);

        $org2Ticket = new Ticket();
        $org2Ticket->organization_id = $this->org2->id;
        $org2Ticket->subject = 'Org2 Hidden';
        $org2Ticket->description = 'Hidden';
        $org2Ticket->status = 'open';
        $org2Ticket->priority = 'high';
        $org2Ticket->requester_id = $this->customer2->id;
        $org2Ticket->saveQuietly();

        $this->withToken($token1)
            ->getJson("/api/tickets/{$org2Ticket->id}")
            ->assertForbidden();
    });

    it('cannot update another org ticket', function () {
        $token1 = loginAs($this->admin1);

        $org2Ticket = new Ticket();
        $org2Ticket->organization_id = $this->org2->id;
        $org2Ticket->subject = 'Org2 No Edit';
        $org2Ticket->description = 'No edit';
        $org2Ticket->status = 'open';
        $org2Ticket->priority = 'high';
        $org2Ticket->requester_id = $this->customer2->id;
        $org2Ticket->saveQuietly();

        $this->withToken($token1)
            ->putJson("/api/tickets/{$org2Ticket->id}", ['status' => 'closed'])
            ->assertForbidden();
    });

    it('cannot delete another org ticket', function () {
        $token1 = loginAs($this->admin1);

        $org2Ticket = new Ticket();
        $org2Ticket->organization_id = $this->org2->id;
        $org2Ticket->subject = 'Org2 No Delete';
        $org2Ticket->description = 'No delete';
        $org2Ticket->status = 'open';
        $org2Ticket->priority = 'high';
        $org2Ticket->requester_id = $this->customer2->id;
        $org2Ticket->saveQuietly();

        $this->withToken($token1)
            ->deleteJson("/api/tickets/{$org2Ticket->id}")
            ->assertForbidden();
    });
});
