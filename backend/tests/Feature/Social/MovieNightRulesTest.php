<?php

namespace Tests\Feature\Social;

use App\Models\User;

class MovieNightRulesTest extends SocialTestCase
{
    private function createNight(User $host, User $guest): int
    {
        return $this->as($host)->postJson('/api/movie-nights', [
            'title' => 'Friday Frights',
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'invitees' => [$guest->username],
            'tmdb_ids' => [1003, 1008],
        ])->assertCreated()->json('night.id');
    }

    public function test_only_invited_guests_can_answer(): void
    {
        $nightId = $this->createNight(User::factory()->create(), User::factory()->create());

        $this->as(User::factory()->create())->putJson("/api/movie-nights/{$nightId}/rsvp", ['status' => 'accepted'])
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not invited to this movie night.');
    }

    public function test_declined_guests_cannot_vote(): void
    {
        $guest = User::factory()->create();
        $nightId = $this->createNight(User::factory()->create(), $guest);

        $this->as($guest)->putJson("/api/movie-nights/{$nightId}/rsvp", ['status' => 'declined'])->assertOk();
        $this->as($guest)->putJson("/api/movie-nights/{$nightId}/vote", ['tmdb_id' => 1003])
            ->assertForbidden()
            ->assertJsonPath('message', 'Declined guests cannot vote.');
    }

    public function test_voting_closes_once_the_host_picks_a_winner(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $nightId = $this->createNight($host, $guest);

        $this->as($host)->patchJson("/api/movie-nights/{$nightId}", ['status' => 'confirmed', 'winning_tmdb_id' => 1003])->assertOk();
        $this->as($guest)->putJson("/api/movie-nights/{$nightId}/vote", ['tmdb_id' => 1008])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Voting is closed for this movie night.');
    }
}
