<?php

namespace Tests\Feature;

use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_register_creates_user_returns_token_and_queues_welcome_email(): void
    {
        Queue::fake();

        $this->postJson('/api/register', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token'])
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);

        Queue::assertPushed(SendWelcomeEmail::class, fn ($job) => $job->user->email === 'jane@example.com');
    }

    public function test_register_validates_input(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/register', $this->payload(['password_confirmation' => 'different']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->postJson('/api/register', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'password123']);

        $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'email'], 'token']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'password123']);

        $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'wrong'])
            ->assertUnauthorized();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_show_user_requires_authentication_and_returns_user(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}")->assertUnauthorized();

        Sanctum::actingAs($user);

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');
    }

    public function test_users_list_is_paginated(): void
    {
        User::factory()->count(12)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/users?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 13);
    }
}
