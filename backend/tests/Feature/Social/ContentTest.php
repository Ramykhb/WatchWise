<?php

namespace Tests\Feature\Social;

use App\Models\MovieList;
use App\Models\User;

class ContentTest extends SocialTestCase
{
    public function test_list_crud_and_reorder(): void
    {
        $user = User::factory()->create();

        $id = $this->as($user)->postJson('/api/lists', ['title' => 'Comedies', 'is_public' => true, 'tmdb_ids' => [3, 1, 2]])
            ->assertCreated()
            ->assertJsonPath('list.films', 3)
            ->assertJsonPath('list.items.0.tmdb_id', 3)
            ->json('list.id');

        $this->as($user)->patchJson("/api/lists/{$id}", ['tmdb_ids' => [2, 3]])
            ->assertOk()
            ->assertJsonPath('list.films', 2)
            ->assertJsonPath('list.items.0.tmdb_id', 2);

        $this->as($user)->getJson('/api/lists?scope=mine')->assertJsonCount(1, 'lists');
        $this->as($user)->deleteJson("/api/lists/{$id}")->assertOk();
        $this->assertDatabaseMissing('movie_lists', ['id' => $id]);
    }

    public function test_lists_are_private_to_their_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = MovieList::create(['user_id' => $owner->id, 'title' => 'Secret', 'is_public' => false]);

        $this->as($other)->getJson("/api/lists/{$list->id}")->assertForbidden();
        $this->as($other)->patchJson("/api/lists/{$list->id}", ['title' => 'Mine now'])->assertForbidden();
        $this->as($other)->deleteJson("/api/lists/{$list->id}")->assertForbidden();
    }

    public function test_following_scope_shows_public_lists_of_followed_users(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        MovieList::create(['user_id' => $friend->id, 'title' => 'Friend public', 'is_public' => true]);
        MovieList::create(['user_id' => $friend->id, 'title' => 'Friend private', 'is_public' => false]);
        MovieList::create(['user_id' => $stranger->id, 'title' => 'Stranger', 'is_public' => true]);
        $me->following()->attach($friend->id);

        $this->as($me)->getJson('/api/lists?scope=following')->assertJsonCount(1, 'lists')->assertJsonPath('lists.0.title', 'Friend public');
        $this->as($me)->getJson('/api/lists?scope=public')->assertJsonCount(2, 'lists');
    }

    public function test_watchlist_flow(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $id = $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 42])->assertCreated()->assertJsonPath('item.status', 'to_watch')->json('item.id');
        $this->as($user)->postJson('/api/watchlist', ['tmdb_id' => 42])->assertOk();
        $this->as($user)->patchJson("/api/watchlist/{$id}", ['status' => 'watched'])->assertOk()->assertJsonPath('item.status', 'watched');
        $this->as($other)->deleteJson("/api/watchlist/{$id}")->assertForbidden();

        $this->as($user)->getJson('/api/users/'.$user->username)->assertJsonPath('counts.films', 1);
        $this->as($user)->deleteJson("/api/watchlist/{$id}")->assertOk();
        $this->as($user)->getJson('/api/watchlist')->assertJsonCount(0, 'items');
    }

    public function test_movie_night_invite_rsvp_and_vote(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $stranger = User::factory()->create();

        $nightId = $this->as($host)->postJson('/api/movie-nights', [
            'title' => 'Friday Frights',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'location' => "Lina's place",
            'invitees' => [$guest->username],
            'tmdb_ids' => [1003, 1008],
        ])->assertCreated()->assertJsonPath('night.my_rsvp', 'host')->json('night.id');

        $this->as($guest)->getJson('/api/movie-nights')->assertJsonCount(1, 'invitations')->assertJsonCount(0, 'upcoming');
        $this->as($stranger)->getJson("/api/movie-nights/{$nightId}")->assertForbidden();

        $this->as($guest)->putJson("/api/movie-nights/{$nightId}/rsvp", ['status' => 'accepted'])->assertOk()->assertJsonPath('night.my_rsvp', 'accepted');
        $this->as($guest)->putJson("/api/movie-nights/{$nightId}/vote", ['tmdb_id' => 1008])->assertOk()->assertJsonPath('night.my_vote', 1008);
        $this->as($guest)->putJson("/api/movie-nights/{$nightId}/vote", ['tmdb_id' => 1003])
            ->assertOk()
            ->assertJsonPath('night.candidates.0.tmdb_id', 1003)
            ->assertJsonCount(1, 'night.candidates.0.voters');

        $this->as($guest)->getJson('/api/movie-nights')->assertJsonCount(1, 'upcoming');
        $this->as($guest)->getJson('/api/home')->assertOk()->assertJsonPath('next_night.id', $nightId);

        // Only the host invites more people.
        $this->as($guest)->postJson("/api/movie-nights/{$nightId}/invites", ['invitees' => [$stranger->username]])->assertForbidden();
        $this->as($host)->postJson("/api/movie-nights/{$nightId}/invites", ['invitees' => [$stranger->username, $guest->username]])
            ->assertOk()
            ->assertJsonCount(2, 'night.guests');

        $this->as($host)->patchJson("/api/movie-nights/{$nightId}", ['status' => 'confirmed', 'winning_tmdb_id' => 1003])
            ->assertOk()->assertJsonPath('night.winning_tmdb_id', 1003);
    }
}
