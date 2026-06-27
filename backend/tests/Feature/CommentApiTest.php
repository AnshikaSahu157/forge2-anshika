<?php

use App\Models\ActivityLog;
use App\Models\Organization;
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

    $this->customer = User::create([
        'organization_id' => $this->org->id,
        'name' => 'Customer',
        'email' => 'customer@test.com',
        'role' => 'customer',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);

    app(TenantContext::class)->setOrganizationId($this->org->id);

    $this->ticket = Ticket::create([
        'organization_id' => $this->org->id,
        'subject' => 'Test Ticket',
        'description' => 'For commenting',
        'status' => 'open',
        'priority' => 'medium',
        'requester_id' => $this->customer->id,
    ]);
});

describe('Comments', function () {
    it('can list comments on a ticket', function () {
        $token = $this->admin->createToken('test')->plainTextToken;

        $this->ticket->comments()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->admin->id,
            'body' => 'First comment',
            'is_internal' => false,
        ]);

        $this->withToken($token)
            ->getJson("/api/tickets/{$this->ticket->id}/comments")
            ->assertOk()
            ->assertJsonPath('0.body', 'First comment');
    });

    it('can create a comment on a ticket', function () {
        $token = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson("/api/tickets/{$this->ticket->id}/comments", [
                'body' => 'New comment via API',
            ]);

        $response->assertCreated()
            ->assertJsonPath('body', 'New comment via API')
            ->assertJsonPath('is_internal', false)
            ->assertJsonPath('user.id', $this->admin->id);
    });

    it('can create an internal comment', function () {
        $token = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson("/api/tickets/{$this->ticket->id}/comments", [
                'body' => 'Internal note',
                'is_internal' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('is_internal', true);
    });

    it('validates required body on comment create', function () {
        $token = $this->admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/tickets/{$this->ticket->id}/comments", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    });

    it('logs activity when a comment is created', function () {
        $token = $this->admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/tickets/{$this->ticket->id}/comments", [
                'body' => 'Activity tracked comment',
            ]);

        $log = ActivityLog::where('ticket_id', $this->ticket->id)
            ->where('action', 'commented')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->user_id)->toBe($this->admin->id);
    });
});

describe('Activity Log', function () {
    it('can view activity log for a ticket', function () {
        $token = $this->admin->createToken('test')->plainTextToken;

        ActivityLog::create([
            'organization_id' => $this->org->id,
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->admin->id,
            'action' => 'created',
            'properties' => ['status' => 'open'],
        ]);

        $this->withToken($token)
            ->getJson("/api/tickets/{$this->ticket->id}/activity")
            ->assertOk()
            ->assertJsonPath('0.action', 'created');
    });

    it('logs activity when ticket is created via API', function () {
        $token = $this->admin->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/tickets', [
            'subject' => 'Tracked ticket',
            'description' => 'Should log creation',
        ]);

        $log = ActivityLog::where('action', 'created')->first();

        expect($log)->not->toBeNull()
            ->and($log->action)->toBe('created');
    });

    it('logs activity when ticket is updated via API', function () {
        $token = $this->admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson("/api/tickets/{$this->ticket->id}", [
                'status' => 'in_progress',
            ]);

        $log = ActivityLog::where('ticket_id', $this->ticket->id)
            ->where('action', 'updated')
            ->first();

        expect($log)->not->toBeNull();
    });
});
