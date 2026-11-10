<?php

namespace Tests\Feature\User;

use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Social\SocialTestCase;

class ProfilePerformanceTest extends SocialTestCase
{
    /** @return list<string> */
    private function queriesDuring(callable $request): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $queries;
    }

    public function test_a_followers_only_profile_checks_the_follow_once_per_view(): void
    {
        $owner = User::factory()->create(['profile_visibility' => 'followers']);
        $viewer = User::factory()->create();
        $viewer->following()->attach($owner->id);

        $queries = $this->queriesDuring(fn () => $this->as($viewer)
            ->getJson("/api/users/{$owner->username}")->assertOk()->assertJsonPath('restricted', false));

        $followChecks = array_filter($queries, fn (string $sql) => str_contains($sql, 'inner join "follows"') && str_contains($sql, 'exists'));
        // Two checks remain: is_following and follows_you (the other direction); the visibility check reuses nothing extra.
        $this->assertLessThanOrEqual(3, count($followChecks), implode("\n", $queries));
    }

    public function test_the_profile_response_is_the_same_for_every_viewer(): void
    {
        $owner = User::factory()->create(['profile_visibility' => 'followers']);
        $follower = User::factory()->create();
        $follower->following()->attach($owner->id);
        $stranger = User::factory()->create();
        $admin = User::factory()->admin()->create();

        foreach ([[$owner, false], [$follower, false], [$admin, false], [$stranger, true]] as [$viewer, $restricted]) {
            $this->as($viewer)->getJson("/api/users/{$owner->username}")->assertOk()
                ->assertJsonPath('restricted', $restricted)
                ->assertJsonPath('is_me', $viewer->is($owner))
                ->assertJsonPath('is_following', $viewer->is($follower))
                ->assertJsonPath('counts.followers', $restricted ? null : 1);
        }
    }

    public function test_recent_followers_and_following_are_the_newest_first(): void
    {
        $owner = User::factory()->create();
        $people = User::factory()->count(10)->create();
        foreach ($people as $person) {
            $person->following()->attach($owner->id);
            $owner->following()->attach($person->id);
        }
        $newest = $people->reverse()->take(8)->pluck('username')->all();

        $response = $this->as(User::factory()->create())->getJson("/api/users/{$owner->username}")->assertOk();

        $this->assertSame($newest, array_column($response->json('followers'), 'username'));
        $this->assertSame($newest, array_column($response->json('following'), 'username'));
    }

    public function test_an_empty_review_is_a_rating_not_a_review_in_the_count(): void
    {
        $owner = User::factory()->create();
        foreach (['Great', '', null] as $index => $content) {
            Review::create(['user_id' => $owner->id, 'tmdb_id' => 100 + $index, 'media_type' => 'movie', 'rating' => 4, 'content' => $content, 'is_approved' => true]);
        }

        $this->as(User::factory()->create())->getJson("/api/users/{$owner->username}")
            ->assertOk()->assertJsonPath('counts.reviews', 1);
    }
}
