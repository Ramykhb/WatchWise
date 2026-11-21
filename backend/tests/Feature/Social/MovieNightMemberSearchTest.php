<?php

namespace Tests\Feature\Social;

use App\Models\User;

class MovieNightMemberSearchTest extends SocialTestCase
{
    public function test_a_member_can_discover_and_invite_nonfollowers_during_creation_and_later(): void
    {
        $host = User::factory()->create();
        $first = User::factory()->create(['name' => 'First Stranger']);
        $second = User::factory()->create(['name' => 'Second Stranger']);
        $this->as($host)->getJson('/api/users?q=Stranger&limit=20')->assertOk()->assertJsonCount(2, 'users');
        $id = $this->postJson('/api/movie-nights', ['title' => 'New members', 'scheduled_at' => now()->addDay()->toISOString(), 'invitees' => [$first->username]])
            ->assertCreated()->assertJsonPath('night.guests.0.user.id', $first->id)->json('night.id');
        $this->postJson("/api/movie-nights/$id/invites", ['invitees' => [$second->username, $first->username]])->assertOk()->assertJsonCount(2, 'night.guests');
        $this->assertDatabaseCount('follows', 0);
        $this->as($second)->getJson("/api/movie-nights/$id")->assertOk();
    }
}
