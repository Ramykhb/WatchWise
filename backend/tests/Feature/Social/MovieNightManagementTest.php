<?php

namespace Tests\Feature\Social;

use App\Models\User;

class MovieNightManagementTest extends SocialTestCase
{
    public function test_only_the_host_can_edit_every_detail_and_cancel_the_night(): void
    {
        [$host, $guest, $stranger] = User::factory()->count(3)->create()->all();
        $id = $this->as($host)->postJson('/api/movie-nights', ['title' => 'Original', 'scheduled_at' => now()->addDays(3)->toISOString(), 'invitees' => [$guest->username]])->assertCreated()->json('night.id');
        $payload = ['title' => 'Updated', 'scheduled_at' => now()->addDays(4)->toISOString(), 'location' => 'New location', 'meeting_link' => 'https://example.com/meet', 'note' => 'Bring popcorn'];
        $this->as($guest)->patchJson("/api/movie-nights/$id", $payload)->assertForbidden();
        $this->as($stranger)->patchJson("/api/movie-nights/$id", ['status' => 'cancelled'])->assertForbidden();
        $this->as($host)->patchJson("/api/movie-nights/$id", $payload)->assertOk()->assertJsonPath('night.title', 'Updated')->assertJsonPath('night.note', 'Bring popcorn');
        $this->as($guest)->getJson("/api/movie-nights/$id")->assertOk()->assertJsonPath('night.location', 'New location')->assertJsonPath('night.meeting_link', 'https://example.com/meet');
        $this->as($host)->patchJson("/api/movie-nights/$id", ['location' => null, 'meeting_link' => null, 'note' => null])->assertOk()->assertJsonPath('night.note', null);
        $this->patchJson("/api/movie-nights/$id", ['status' => 'cancelled'])->assertOk()->assertJsonPath('night.status', 'cancelled');
        $this->as($guest)->getJson('/api/movie-nights')->assertJsonCount(0, 'invitations');
        $this->getJson("/api/movie-nights/$id")->assertOk()->assertJsonPath('night.status', 'cancelled');
        $this->putJson("/api/movie-nights/$id/vote", ['tmdb_id' => 42])->assertUnprocessable();
        $this->putJson("/api/movie-nights/$id/rsvp", ['status' => 'accepted'])->assertUnprocessable();
        $this->as($host)->postJson("/api/movie-nights/$id/invites", ['invitees' => [$stranger->username]])->assertUnprocessable();
    }
}
