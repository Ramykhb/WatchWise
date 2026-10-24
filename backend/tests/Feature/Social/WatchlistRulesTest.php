<?php

namespace Tests\Feature\Social;

use App\Models\Review;
use App\Models\User;

class WatchlistRulesTest extends SocialTestCase
{
    public function test_only_the_owner_can_change_a_row(): void
    {
        $id = $this->as(User::factory()->create())->postJson('/api/watchlist', ['tmdb_id' => 42])->json('item.id');

        // Authorization comes before validation, so an invalid body from a stranger is still a 403.
        $this->as(User::factory()->create())->patchJson("/api/watchlist/{$id}", ['status' => 'nonsense'])->assertForbidden();
    }

    public function test_watched_at_follows_the_status(): void
    {
        $user = User::factory()->create();

        $id = $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 42, 'status' => 'watched'])
            ->assertCreated()
            ->assertJsonPath('item.status', 'watched')
            ->json('item.id');
        $this->assertNotNull($this->as($user)->getJson('/api/watchlist')->json('items.0.watched_at'));

        $this->as($user)->patchJson("/api/watchlist/{$id}", ['status' => 'watching'])
            ->assertOk()
            ->assertJsonPath('item.watched_at', null);
    }

    public function test_adding_again_without_a_status_keeps_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 42, 'status' => 'watching'])->assertCreated();
        $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 42])->assertOk()->assertJsonPath('item.status', 'watching');
    }

    public function test_status_must_be_a_known_value(): void
    {
        $this->as(User::factory()->create())->postJson('/api/watchlist', ['tmdb_id' => 42, 'status' => 'nonsense'])
            ->assertUnprocessable();
    }

    public function test_overview_includes_the_users_ratings(): void
    {
        $user = User::factory()->create();
        Review::forceCreate(['user_id' => $user->id, 'tmdb_id' => 42, 'content' => 'Great', 'rating' => 5, 'sentiment' => 'positive']);

        $this->as($user)->getJson('/api/watchlist')->assertOk()->assertJsonPath('ratings.42', 5);
    }
}
