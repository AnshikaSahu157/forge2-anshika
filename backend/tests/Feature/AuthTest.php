<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->org = Organization::create([
        'name' => 'Test Org',
        'slug' => 'test-org',
    ]);
});

describe('Registration', function () {
    it('can register a new user', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_id' => $this->org->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token']);

        expect(User::where('email', 'john@test.com')->exists())->toBeTrue();
    });

    it('validates required fields on register', function () {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'organization_id']);
    });

    it('validates email format on register', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_id' => $this->org->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates password confirmation on register', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John',
            'email' => 'john@test.com',
            'password' => 'password123',
            'password_confirmation' => 'wrong',
            'organization_id' => $this->org->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    it('sets role to customer on register', function () {
        $response = $this->postJson('/api/register', [
            'name' => 'John',
            'email' => 'john@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'organization_id' => $this->org->id,
        ]);

        expect($response->json('user.role'))->toBe('customer');
    });
});

describe('Login', function () {
    beforeEach(function () {
        $this->user = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Jane Doe',
            'email' => 'jane@test.com',
            'role' => 'admin',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);
    });

    it('can login with valid credentials', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'jane@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);
    });

    it('returns 401 for invalid credentials', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'jane@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Invalid credentials']);
    });

    it('returns 401 for non-existent user', function () {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@test.com',
            'password' => 'password123',
        ]);

        $response->assertUnauthorized();
    });

    it('validates required fields on login', function () {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    });
});

describe('Logout', function () {
    it('can logout and revoke token', function () {
        $user = User::create([
            'organization_id' => $this->org->id,
            'name' => 'Logout User',
            'email' => 'logout@test.com',
            'role' => 'admin',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out']);

        // Token should be revoked
        expect($user->tokens()->count())->toBe(0);
    });

    it('requires authentication for logout', function () {
        $this->postJson('/api/logout')->assertUnauthorized();
    });
});
