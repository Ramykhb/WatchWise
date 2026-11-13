<?php

namespace Tests\Feature\MovieList;

use App\Models\MovieList;
use App\Models\User;
use App\MovieList\DTOs\Requests\MovieListSearchQuery;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Social\SocialTestCase;

class MovieListPaginationTest extends SocialTestCase
{
    /**
     * Lists "List 1".."List N", each updated one minute after the previous one (so the newest has the highest number).
     *
     * @return list<MovieList>
     */
    private function makeLists(User $owner, int $count, bool $public = false): array
    {
        $base = Carbon::parse('2026-01-01 12:00:00');
        $lists = [];

        for ($i = 1; $i <= $count; $i++) {
            $list = MovieList::create(['user_id' => $owner->id, 'title' => "List {$i}", 'is_public' => $public]);
            $list->forceFill(['updated_at' => $base->copy()->addMinutes($i)])->saveQuietly();
            $lists[] = $list;
        }

        return $lists;
    }

    /** @return list<string> */
    private function titles(TestResponse $response): array
    {
        return array_column($response->json('lists'), 'title');
    }

    public function test_pages_are_consecutive_slices_newest_first_with_has_more(): void
    {
        $owner = User::factory()->create();
        $this->makeLists($owner, 5);

        $first = $this->as($owner)->getJson('/api/lists?scope=mine&per_page=2&page=1')->assertOk();
        $second = $this->as($owner)->getJson('/api/lists?scope=mine&per_page=2&page=2')->assertOk();
        $third = $this->as($owner)->getJson('/api/lists?scope=mine&per_page=2&page=3')->assertOk();

        $this->assertSame(['List 5', 'List 4'], $this->titles($first));
        $first->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 2, 'has_more' => true]);
        $this->assertSame(['List 3', 'List 2'], $this->titles($second));
        $second->assertJsonPath('meta.has_more', true);
        $this->assertSame(['List 1'], $this->titles($third));
        $third->assertJsonPath('meta', ['current_page' => 3, 'per_page' => 2, 'has_more' => false]);
    }

    public function test_an_exact_final_page_reports_no_more_and_a_page_past_the_end_is_empty(): void
    {
        $owner = User::factory()->create();
        $this->makeLists($owner, 4);

        $this->as($owner)->getJson('/api/lists?scope=mine&per_page=2&page=2')
            ->assertJsonCount(2, 'lists')->assertJsonPath('meta.has_more', false);
        $this->as($owner)->getJson('/api/lists?scope=mine&per_page=2&page=3')
            ->assertOk()->assertJsonCount(0, 'lists')->assertJsonPath('meta.has_more', false);
    }

    public function test_lists_updated_at_the_same_moment_are_ordered_by_newest_id_without_gaps_or_repeats(): void
    {
        $owner = User::factory()->create();
        $moment = Carbon::parse('2026-01-01 12:00:00');
        $ids = [];

        for ($i = 1; $i <= 5; $i++) {
            $list = MovieList::create(['user_id' => $owner->id, 'title' => "Tie {$i}", 'is_public' => false]);
            $list->forceFill(['updated_at' => $moment])->saveQuietly();
            $ids[] = $list->id;
        }

        $seen = [];
        foreach ([1, 2, 3] as $page) {
            $response = $this->as($owner)->getJson("/api/lists?scope=mine&per_page=2&page={$page}");
            $seen = [...$seen, ...array_column($response->json('lists'), 'id')];
        }

        $this->assertSame(array_reverse($ids), $seen);
    }

    public function test_a_member_with_more_lists_than_the_old_cap_can_reach_all_of_them(): void
    {
        $owner = User::factory()->create();
        $this->makeLists($owner, 230);

        $seen = [];
        $page = 1;
        do {
            $response = $this->as($owner)->getJson("/api/lists?scope=mine&page={$page}")->assertOk();
            $this->assertLessThanOrEqual(MovieListSearchQuery::DEFAULT_PER_PAGE_MINE, count($response->json('lists')));
            $seen = [...$seen, ...array_column($response->json('lists'), 'id')];
            $page++;
        } while ($response->json('meta.has_more'));

        $this->assertCount(230, $seen);
        $this->assertCount(230, array_unique($seen));
        $this->assertSame(3, $page - 1);
    }

    public function test_default_page_sizes_are_100_for_mine_and_30_for_other_scopes(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->following()->attach($owner->id);
        $this->makeLists($owner, 31, public: true);

        $this->as($owner)->getJson('/api/lists?scope=mine')
            ->assertJsonPath('meta.per_page', 100)->assertJsonCount(31, 'lists')->assertJsonPath('meta.has_more', false);

        foreach (['public', 'following'] as $scope) {
            $this->as($viewer)->getJson("/api/lists?scope={$scope}")
                ->assertJsonPath('meta.per_page', 30)->assertJsonCount(30, 'lists')->assertJsonPath('meta.has_more', true);
            $this->as($viewer)->getJson("/api/lists?scope={$scope}&page=2")
                ->assertJsonCount(1, 'lists')->assertJsonPath('meta.has_more', false);
        }
    }

    public function test_paging_keeps_the_search_filter(): void
    {
        $owner = User::factory()->create();
        $this->makeLists($owner, 3);
        MovieList::create(['user_id' => $owner->id, 'title' => 'Other', 'is_public' => false]);

        $response = $this->as($owner)->getJson('/api/lists?scope=mine&q=List&per_page=2&page=2')->assertOk();

        $this->assertSame(['List 1'], $this->titles($response));
        $response->assertJsonPath('meta.has_more', false);
    }

    public function test_page_and_per_page_are_validated(): void
    {
        $owner = User::factory()->create();

        foreach (['per_page=0', 'per_page=101', 'per_page=abc', 'page=0', 'page=-1', 'page=abc'] as $query) {
            $this->as($owner)->getJson("/api/lists?scope=mine&{$query}")->assertUnprocessable();
        }

        $this->as($owner)->getJson('/api/lists?scope=mine&per_page=100&page=1')->assertOk();
        $this->as($owner)->getJson('/api/lists?scope=mine&per_page=1')->assertOk();
    }
}
