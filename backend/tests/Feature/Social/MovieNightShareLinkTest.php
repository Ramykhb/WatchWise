<?php

namespace Tests\Feature\Social;

use App\Models\MovieNight;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MovieNightShareLinkTest extends SocialTestCase
{
    private function night(User $host): MovieNight
    {
        return MovieNight::create(['organizer_id' => $host->id, 'title' => 'Secret night', 'scheduled_at' => now()->addDays(2), 'location' => 'Private address', 'note' => 'Private note']);
    }

    private function link(MovieNight $night, User $host): array
    {
        $data = $this->as($host)->postJson("/api/movie-nights/$night->id/invite-link")->assertOk()->json();
        parse_str(parse_url($data['url'], PHP_URL_QUERY), $query);

        return [$data['url'], $query['token']];
    }

    public function test_link_preview_and_explicit_join_grant_access_without_following_and_are_idempotent(): void
    {
        [$host, $guest] = User::factory()->count(2)->create()->all();
        $night = $this->night($host);
        [$url, $token] = $this->link($night, $host);
        $this->assertStringContainsString("/movie-nights/$night->id/join?token=", $url);
        $this->assertNotSame($token, DB::table('movie_nights')->where('id', $night->id)->value('share_token'));
        $this->as($guest)->getJson("/api/movie-nights/$night->id")->assertForbidden();
        $this->getJson("/api/movie-nights/$night->id/invitation?token=$token")->assertOk()
            ->assertExactJson(['title' => 'Secret night', 'host_name' => $host->name, 'scheduled_at' => $night->scheduled_at->toJSON()]);
        $this->assertDatabaseCount('movie_night_invites', 0);
        foreach (range(1, 2) as $_) {
            $this->postJson("/api/movie-nights/$night->id/join", ['token' => $token])->assertOk()->assertJsonPath('night.my_rsvp', 'accepted')->assertJsonPath('night.location', 'Private address');
        }
        $this->getJson("/api/movie-nights/$night->id")->assertOk();
        $this->assertDatabaseCount('movie_night_invites', 1);
    }

    public function test_only_hosts_can_manage_links_and_revocation_invalidates_old_copies(): void
    {
        [$host, $guest] = User::factory()->count(2)->create()->all();
        $night = $this->night($host);
        [, $token] = $this->link($night, $host);
        $this->as($guest)->postJson("/api/movie-nights/$night->id/invite-link")->assertForbidden();
        $this->deleteJson("/api/movie-nights/$night->id/invite-link")->assertForbidden();
        $this->as($host)->deleteJson("/api/movie-nights/$night->id/invite-link")->assertOk();
        $this->as($guest)->postJson("/api/movie-nights/$night->id/join", ['token' => $token])->assertNotFound();
        [, $newToken] = $this->link($night, $host);
        $this->assertNotSame($token, $newToken);
    }

    public function test_expired_cancelled_and_tampered_links_do_not_create_invitations(): void
    {
        [$host, $guest] = User::factory()->count(2)->create()->all();
        $night = $this->night($host);
        [, $token] = $this->link($night, $host);
        $this->as($guest)->postJson("/api/movie-nights/$night->id/join", ['token' => str_repeat('x', 64)])->assertNotFound();
        $night->update(['share_expires_at' => now()->subMinute()]);
        $this->postJson("/api/movie-nights/$night->id/join", ['token' => $token])->assertGone();
        $night->update(['share_expires_at' => now()->addDay(), 'status' => 'cancelled']);
        $this->getJson("/api/movie-nights/$night->id/invitation?token=$token")->assertGone();
        $this->postJson("/api/movie-nights/$night->id/join", ['token' => $token])->assertGone();
        $this->assertDatabaseCount('movie_night_invites', 0);
    }
}
