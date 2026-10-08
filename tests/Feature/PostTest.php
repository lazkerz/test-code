<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_paginated(): void
    {
        Post::factory()->count(20)->create();

        $this->getJson('/api/posts?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_index_rejects_invalid_per_page(): void
    {
        $this->getJson('/api/posts?per_page=1000')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_show_returns_post_with_author(): void
    {
        $post = Post::factory()->create();

        $this->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.author.id', $post->user_id);
    }

    public function test_show_returns_404_for_missing_post(): void
    {
        $this->getJson('/api/posts/999')->assertNotFound();
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/posts', ['title' => 'A', 'body' => 'B'])->assertUnauthorized();
    }

    public function test_store_validates_input(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/posts', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_store_creates_post_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/posts', ['title' => 'Hello', 'body' => 'World'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Hello');

        $this->assertDatabaseHas('posts', ['user_id' => $user->id, 'title' => 'Hello']);
    }

    public function test_owner_can_update_post(): void
    {
        $post = Post::factory()->create();
        Sanctum::actingAs($post->user);

        $this->patchJson("/api/posts/{$post->id}", ['title' => 'Changed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Changed');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'body' => $post->body]);
    }

    public function test_non_owner_cannot_update_or_delete_post(): void
    {
        $post = Post::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/posts/{$post->id}", ['title' => 'Nope'])->assertForbidden();
        $this->deleteJson("/api/posts/{$post->id}")->assertForbidden();
    }

    public function test_owner_can_delete_post(): void
    {
        $post = Post::factory()->create();
        Sanctum::actingAs($post->user);

        $this->deleteJson("/api/posts/{$post->id}")->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

}
