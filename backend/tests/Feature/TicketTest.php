<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    // Create two organizations
    $this->org1 = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
    ]);

    $this->org2 = Organization::create([
        'name' => 'Beta Inc',
        'slug' => 'beta-inc',
    ]);

    // Users for org1
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

    // Users for org2
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

    // Set session to org1 for scoped queries
    Session::put('organization_id', $this->org1->id);
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
        // Create ticket in org1
        $org1Ticket = Ticket::create([
            'organization_id' => $this->org1->id,
            'subject' => 'Org1 Ticket',
            'description' => 'Belongs to org1',
            'status' => 'open',
            'priority' => 'medium',
            'requester_id' => $this->customer1->id,
        ]);

        // Create ticket in org2
        $org2Ticket = Ticket::factory()->create([
            'organization_id' => $this->org2->id,
            'requester_id' => User::factory()->create([
                'organization_id' => $this->org2->id,
            ])->id,
        ]);

        // With session set to org1, we should only see org1 tickets
        Session::put('organization_id', $this->org1->id);
        $visibleTickets = Ticket::all();

        expect($visibleTickets)->toHaveCount(1)
            ->and($visibleTickets->first()->id)->toBe($org1Ticket->id)
            ->and($visibleTickets->pluck('id'))->not->toContain($org2Ticket->id);
    });

    it('cannot access another organization ticket by ID when scoped', function () {
        // Create a ticket in org2
        $org2Ticket = Ticket::factory()->create([
            'organization_id' => $this->org2->id,
            'requester_id' => User::factory()->create([
                'organization_id' => $this->org2->id,
            ])->id,
        ]);

        // Switch session to org1
        Session::put('organization_id', $this->org1->id);

        // Attempt to find org2's ticket while scoped to org1
        $found = Ticket::find($org2Ticket->id);

        expect($found)->toBeNull();
    });

    it('returns different ticket sets for different organizations', function () {
        // Create tickets for both orgs
        Ticket::create([
            'organization_id' => $this->org1->id,
            'subject' => 'Acme Only',
            'description' => 'Only visible to Acme',
            'status' => 'open',
            'priority' => 'low',
            'requester_id' => $this->customer1->id,
        ]);

        $org2Customer = User::factory()->create([
            'organization_id' => $this->org2->id,
        ]);

        Ticket::create([
            'organization_id' => $this->org2->id,
            'subject' => 'Beta Only',
            'description' => 'Only visible to Beta',
            'status' => 'open',
            'priority' => 'high',
            'requester_id' => $org2Customer->id,
        ]);

        // Check org1 visibility
        Session::put('organization_id', $this->org1->id);
        $org1Tickets = Ticket::all();
        expect($org1Tickets)->toHaveCount(1)
            ->and($org1Tickets->first()->subject)->toBe('Acme Only');

        // Check org2 visibility
        Session::put('organization_id', $this->org2->id);
        $org2Tickets = Ticket::all();
        expect($org2Tickets)->toHaveCount(1)
            ->and($org2Tickets->first()->subject)->toBe('Beta Only');
    });
});
