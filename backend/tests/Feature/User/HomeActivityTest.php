<?php

namespace Tests\Feature\User;

use App\Models\DiscussionRoom;
use App\Models\MovieList;
use App\Models\Review;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\Watchlist;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Social\SocialTestCase;

class HomeActivityTest extends SocialTestCase
{
    public function test_home_requires_authentication(): void
    {
        $this->getJson('/api/home')->assertUnauthorized();
    }

    public function test_home_returns_an_empty_feed_without_follows(): void
    {
        $this->as(User::factory()->create())->getJson('/api/home')
            ->assertOk()->assertJsonPath('activity', []);
    }

    public function test_saved_ratings_lists_and_watches_appear_for_followers_with_tv_types(): void
    {
        $this->freezeTime();
        $this->travel(-1)->minutes();
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        $this->as($friend)->putJson('/api/ratings/42', ['rating' => 5, 'type' => 'tv'])->assertOk();
        $this->travel(1)->seconds();
        $listId = $this->as($friend)->postJson('/api/lists', ['title' => 'Great shows', 'is_public' => true, 'tmdb_ids' => ['tv-42']])
            ->assertCreated()->json('list.id');
        $this->travel(1)->seconds();
        $this->as($friend)->postJson('/api/watchlist', ['tmdb_id' => 42, 'media_type' => 'tv', 'status' => 'watched'])->assertCreated();

        $this->as($viewer)->getJson('/api/home')->assertOk()->assertJsonCount(3, 'activity')
            ->assertJsonPath('activity.0.kind', 'watched')
            ->assertJsonPath('activity.0.media_type', 'tv')
            ->assertJsonPath('activity.0.user.username', $friend->username)
            ->assertJsonPath('activity.1.kind', 'list')
            ->assertJsonPath('activity.1.list.id', $listId)
            ->assertJsonPath('activity.1.tmdb_id', 42)
            ->assertJsonPath('activity.1.media_type', 'tv')
            ->assertJsonPath('activity.2.kind', 'rated')
            ->assertJsonPath('activity.2.rating', 5)
            ->assertJsonPath('activity.2.media_type', 'tv');
    }

    public function test_feed_excludes_self_strangers_private_inactive_and_pending_follows(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create(['profile_visibility' => 'followers']);
        $private = User::factory()->private()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        $pending = User::factory()->create(['profile_visibility' => 'followers']);
        $stranger = User::factory()->create();
        $viewer->following()->attach([$friend->id, $private->id, $inactive->id]);
        $this->as($viewer)->postJson('/api/users/'.$pending->username.'/follow')->assertOk()->assertJsonPath('requested', true);
        foreach ([$viewer, $friend, $private, $inactive, $pending, $stranger] as $author) {
            $this->rating($author);
        }

        $this->as($viewer)->getJson('/api/home')->assertOk()->assertJsonCount(1, 'activity')
            ->assertJsonPath('activity.0.user.id', $friend->id);
    }

    public function test_watchlist_requires_opt_in_and_history_has_its_own_switch(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        Watchlist::create(['user_id' => $friend->id, 'tmdb_id' => 1, 'status' => 'to_watch']);
        Watchlist::create(['user_id' => $friend->id, 'tmdb_id' => 2, 'status' => 'watched', 'watched_at' => now()]);
        $this->as($viewer)->getJson('/api/home')->assertOk()->assertJsonCount(1, 'activity')->assertJsonPath('activity.0.kind', 'watched');

        $this->as($friend)->patchJson('/api/me/preferences', ['privacy' => ['watchlist' => true, 'history' => false]])->assertOk();

        $this->as($viewer)->getJson('/api/home')->assertOk()->assertJsonCount(1, 'activity')
            ->assertJsonPath('activity.0.kind', 'watchlist')->assertJsonPath('activity.0.tmdb_id', 1);
    }

    public function test_review_rating_and_list_privacy_are_enforced_without_leaking_text_or_stars(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        $this->rating($friend);
        $this->rating($friend, ['tmdb_id' => 2, 'content' => 'A brilliant film.']);
        MovieList::create(['user_id' => $friend->id, 'title' => 'Shared', 'is_public' => true]);
        MovieList::create(['user_id' => $friend->id, 'title' => 'Secret', 'is_public' => false]);
        UserPreference::create(['user_id' => $friend->id, 'settings' => ['privacy' => ['ratings' => false, 'lists' => false]]]);

        $this->as($viewer)->getJson('/api/home')->assertOk()->assertJsonCount(1, 'activity')
            ->assertJsonPath('activity.0.kind', 'reviewed')
            ->assertJsonPath('activity.0.excerpt', 'A brilliant film.')
            ->assertJsonPath('activity.0.rating', null);

        $this->as($friend)->patchJson('/api/me/preferences', ['privacy' => ['reviews' => false, 'ratings' => true, 'lists' => true]])->assertOk();
        $response = $this->as($viewer)->getJson('/api/home')->assertOk()->assertJsonCount(2, 'activity');
        $this->assertSame(['list', 'rated'], collect($response->json('activity'))->pluck('kind')->sort()->values()->all());
        $response->assertJsonMissing(['excerpt' => 'A brilliant film.'])->assertJsonMissing(['title' => 'Secret']);
    }

    public function test_feed_rechecks_moderation_deletions_and_unfollows(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        $visible = $this->rating($friend);
        $this->rating($friend, ['tmdb_id' => 2, 'is_approved' => false]);
        $this->rating($friend, ['tmdb_id' => 3, 'removed_at' => now()]);
        $this->as($viewer)->getJson('/api/home')->assertOk()->assertJsonCount(1, 'activity');
        $visible->delete();
        $this->as($viewer)->getJson('/api/home')->assertOk()->assertJsonPath('activity', []);
        $this->rating($friend);
        $this->as($viewer)->deleteJson('/api/users/'.$friend->username.'/follow')->assertOk();

        $this->as($viewer)->getJson('/api/home')->assertOk()->assertJsonPath('activity', []);
    }

    public function test_feed_hides_spoiler_text_and_limits_the_latest_activity_to_eight_items(): void
    {
        $this->freezeTime();
        $this->travel(-1)->minutes();
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        for ($id = 1; $id <= 10; $id++) {
            $this->rating($friend, ['tmdb_id' => $id]);
            $this->travel(1)->seconds();
        }
        $spoiler = $this->rating($friend, ['tmdb_id' => 11, 'content' => 'The ending revealed.', 'has_spoilers' => true]);

        $this->as($viewer)->getJson('/api/home')->assertOk()->assertJsonCount(8, 'activity')
            ->assertJsonPath('activity.0.id', 'review:'.$spoiler->id)
            ->assertJsonPath('activity.0.kind', 'reviewed')
            ->assertJsonPath('activity.0.excerpt', null)
            ->assertJsonPath('activity.0.has_spoilers', true)
            ->assertJsonPath('activity.7.tmdb_id', 4)
            ->assertJsonMissing(['excerpt' => 'The ending revealed.']);
    }

    private function rating(User $user, array $attributes = []): Review
    {
        return Review::create(array_merge([
            'user_id' => $user->id, 'tmdb_id' => 1, 'media_type' => 'movie',
            'rating' => 4, 'content' => null, 'is_approved' => true,
        ], $attributes));
    }

    public function test_home_rooms_report_their_member_counts_without_a_query_per_room(): void
    {
        $viewer = User::factory()->create();
        foreach ([2, 3, 1] as $index => $members) {
            $room = DiscussionRoom::create(['tmdb_id' => 600 + $index, 'name' => "Room {$index}"]);
            $room->members()->attach(User::factory()->count($members)->create()->modelKeys());
        }

        DB::enableQueryLog();
        $response = $this->as($viewer)->getJson('/api/home')->assertOk();
        $memberQueries = array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $sql) => str_contains($sql, 'room_members') && ! str_contains($sql, 'select "discussion_rooms".*'));
        DB::disableQueryLog();

        $this->assertEqualsCanonicalizing([1, 2, 3], array_column($response->json('rooms'), 'members'));
        $this->assertCount(0, $memberQueries);
    }
}
