<?php

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TenantContext;

function setTenant(int $orgId): void
{
    app(TenantContext::class)->setOrganizationId($orgId);
}

function clearTenant(): void
{
    app(TenantContext::class)->clear();
}

beforeEach(function () {
    $this->org1 = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
    ]);

    $this->org2 = Organization::create([
        'name' => 'Beta Inc',
        'slug' => 'beta-inc',
    ]);

    $this->admin1 = User::create([
        'organization_id' => $this->org1->id,
        'name' => 'Admin One',
        'email' => 'admin1@test.com',
        'role' => 'admin',
        'email_verified_at' => now(),
        'password' => bcrypt('password'),
    ]);

    $this->agent1 = User::create([
        'organization_id' => $this->org1->id,
        'name' => 'Agent One',
        'email' => 'agent1@test.com',
        'role' => 'agent',
        'email_verified_at' => now(),
        'password' => bcrypt('password'),
    ]);

    $this->customer1 = User::create([
        'organization_id' => $this->org1->id,
        'name' => 'Customer One',
        'email' => 'cust1@test.com',
        'role' => 'customer',
        'email_verified_at' => now(),
        'password' => bcrypt('password'),
    ]);

    $this->admin2 = User::create([
        'organization_id' => $this->org2->id,
        'name' => 'Admin Two',
        'email' => 'admin2@test.com',
        'role' => 'admin',
        'email_verified_at' => now(),
        'password' => bcrypt('password'),
    ]);

    $this->customer2 = User::create([
        'organization_id' => $this->org2->id,
        'name' => 'Customer Two',
        'email' => 'cust2@test.com',
        'role' => 'customer',
        'email_verified_at' => now(),
        'password' => bcrypt('password'),
    ]);

    setTenant($this->org1->id);
});

afterEach(function () {
    clearTenant();
});

describe('Ticket CRUD', function () {
    it('can create a ticket', function () {
        $ticket = Ticket::create([
            'organization_id' => $this->org1->id,
            'subject' => 'New bug report',
            'description' => 'Something is broken',
            'status' => 'open',
            'priority' => 'high',
            'requester_id' => $this->customer1->id,
            'assignee_id' => $this->agent1->id,
            'tags' => ['bug', 'urgent'],
        ]);

        expect($ticket)->toBeInstanceOf(Ticket::class)
            ->and($ticket->subject)->toBe('New bug report')
            ->and($ticket->status)->toBe('open')
            ->and($ticket->priority)->toBe('high')
            ->and($ticket->tags)->toBe(['bug', 'urgent']);

        $this->assertDatabaseHas('tickets', [
            'subject' => 'New bug report',
            'organization_id' => $this->org1->id,
        ]);
    });

    it('auto-fills organization_id from tenant context on create', function () {
        $ticket = new Ticket();
        $ticket->subject = 'Auto-org ticket';
        $ticket->description = 'org_id should be auto-filled';
        $ticket->status = 'open';
        $ticket->priority = 'medium';
        $ticket->requester_id = $this->customer1->id;
        $ticket->save();

        expect($ticket->organization_id)->toBe($this->org1->id);
    });

    it('can read a ticket', function () {
        $ticket = Ticket::create([
            'organization_id' => $this->org1->id,
            'subject' => 'Read me',
            'description' => 'Read test',
            'status' => 'open',
            'priority' => 'medium',
            'requester_id' => $this->customer1->id,
        ]);

        $found = Ticket::find($ticket->id);

        expect($found)->not->toBeNull()
            ->and($found->subject)->toBe('Read me')
            ->and($found->description)->toBe('Read test');
    });

    it('can update a ticket', function () {
        $ticket = Ticket::create([
            'organization_id' => $this->org1->id,
            'subject' => 'Original subject',
            'description' => 'Original desc',
            'status' => 'open',
            'priority' => 'low',
            'requester_id' => $this->customer1->id,
        ]);

        $ticket->update([
            'status' => 'in_progress',
            'priority' => 'urgent',
            'assignee_id' => $this->agent1->id,
        ]);

        expect($ticket->fresh()->status)->toBe('in_progress')
            ->and($ticket->fresh()->priority)->toBe('urgent')
            ->and($ticket->fresh()->assignee_id)->toBe($this->agent1->id);
    });

    it('can delete a ticket', function () {
        $ticket = Ticket::create([
            'organization_id' => $this->org1->id,
            'subject' => 'Delete me',
            'description' => 'Delete test',
            'status' => 'closed',
            'priority' => 'low',
            'requester_id' => $this->customer1->id,
        ]);

        $ticketId = $ticket->id;
        $ticket->delete();

        $this->assertDatabaseMissing('tickets', ['id' => $ticketId]);
    });

    it('can list tickets within an organization', function () {
        Ticket::create([
            'organization_id' => $this->org1->id,
            'subject' => 'Ticket A',
            'description' => 'Desc A',
            'status' => 'open',
            'priority' => 'high',
            'requester_id' => $this->customer1->id,
        ]);

        Ticket::create([
            'organization_id' => $this->org1->id,
            'subject' => 'Ticket B',
            'description' => 'Desc B',
            'status' => 'open',
            'priority' => 'medium',
            'requester_id' => $this->customer1->id,
        ]);

        $tickets = Ticket::all();

        expect($tickets)->toHaveCount(2)
            ->and($tickets->pluck('subject')->toArray())->toContain('Ticket A', 'Ticket B');
    });
});

describe('Cross-Tenant Isolation', function () {
    it('does not return tickets from another organization via global scope', function () {
        $org1Ticket = Ticket::create([
            'organization_id' => $this->org1->id,
            'subject' => 'Org1 Ticket',
            'description' => 'Belongs to org1',
            'status' => 'open',
            'priority' => 'medium',
            'requester_id' => $this->customer1->id,
        ]);

        // Bypass scope to create org2 ticket
        $org2Id = $this->org2->id;
        $org2UserId = User::factory()->create(['organization_id' => $org2Id])->id;

        $org2Ticket = new Ticket();
        $org2Ticket->organization_id = $org2Id;
        $org2Ticket->subject = 'Org2 Ticket';
        $org2Ticket->description = 'Belongs to org2';
        $org2Ticket->status = 'open';
        $org2Ticket->priority = 'low';
        $org2Ticket->requester_id = $org2UserId;
        $org2Ticket->saveQuietly(); // bypass creating hook + scope

        setTenant($this->org1->id);
        $visibleTickets = Ticket::all();

        expect($visibleTickets)->toHaveCount(1)
            ->and($visibleTickets->first()->id)->toBe($org1Ticket->id)
            ->and($visibleTickets->pluck('id'))->not->toContain($org2Ticket->id);
    });

    it('cannot access another organization ticket by ID when scoped', function () {
        $org2Id = $this->org2->id;
        $org2UserId = User::factory()->create(['organization_id' => $org2Id])->id;

        $org2Ticket = new Ticket();
        $org2Ticket->organization_id = $org2Id;
        $org2Ticket->subject = 'Org2 Secret';
        $org2Ticket->description = 'Should not be visible to org1';
        $org2Ticket->status = 'open';
        $org2Ticket->priority = 'high';
        $org2Ticket->requester_id = $org2UserId;
        $org2Ticket->saveQuietly();

        setTenant($this->org1->id);

        $found = Ticket::find($org2Ticket->id);

        expect($found)->toBeNull();
    });

    it('returns different ticket sets for different organizations', function () {
        Ticket::create([
            'organization_id' => $this->org1->id,
            'subject' => 'Acme Only',
            'description' => 'Only visible to Acme',
            'status' => 'open',
            'priority' => 'low',
            'requester_id' => $this->customer1->id,
        ]);

        $org2Id = $this->org2->id;
        $org2UserId = User::factory()->create(['organization_id' => $org2Id])->id;

        $betaTicket = new Ticket();
        $betaTicket->organization_id = $org2Id;
        $betaTicket->subject = 'Beta Only';
        $betaTicket->description = 'Only visible to Beta';
        $betaTicket->status = 'open';
        $betaTicket->priority = 'high';
        $betaTicket->requester_id = $org2UserId;
        $betaTicket->saveQuietly();

        setTenant($this->org1->id);
        $org1Tickets = Ticket::all();
        expect($org1Tickets)->toHaveCount(1)
            ->and($org1Tickets->first()->subject)->toBe('Acme Only');

        setTenant($this->org2->id);
        $org2Tickets = Ticket::all();
        expect($org2Tickets)->toHaveCount(1)
            ->and($org2Tickets->first()->subject)->toBe('Beta Only');
    });

    it('middleware sets tenant context from authenticated user', function () {
        $this->actingAs($this->admin1, 'sanctum');

        $response = $this->getJson('/api/user');

        $response->assertOk();
        expect(app(TenantContext::class)->getOrganizationId())->toBe($this->org1->id);
    });
});
