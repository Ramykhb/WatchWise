<?php

namespace Tests\Feature\Admin;

use App\Models\LocalMovie;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;

/** The staff view of every member's movie lists: who may read it, what a row and the detail show, the filters and the summary. */
class AdminListsTest extends AdminListsTestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: bool}> method, uri, whether moderators may call it
     */
    public static function listRoutes(): array
    {
        return [
            'list' => ['getJson', '/api/admin/lists', true],
            'show' => ['getJson', '/api/admin/lists/1', true],
            'make private' => ['patchJson', '/api/admin/lists/1', true],
            'remove item' => ['deleteJson', '/api/admin/lists/1/items/550', true],
            'delete' => ['deleteJson', '/api/admin/lists/1', false],
        ];
    }

    #[DataProvider('listRoutes')]
    public function test_guests_are_unauthenticated(string $method, string $uri): void
    {
        $this->$method($uri)->assertUnauthorized();
    }

    #[DataProvider('listRoutes')]
    public function test_members_get_forbidden(string $method, string $uri): void
    {
        $this->listOf(User::factory()->create(), films: [[550]]);

        $this->as(User::factory()->create())->$method($uri)->assertForbidden();
    }

    #[DataProvider('listRoutes')]
    public function test_the_role_decides_who_may_call_each_route(string $method, string $uri, bool $moderatorMayCall): void
    {
        $owner = User::factory()->create();
        $forModerator = $this->listOf($owner, films: [[550]]);
        $forAdmin = $this->listOf($owner, films: [[550]]);
        $body = $method === 'patchJson' ? ['visibility' => 'private'] : [];

        $moderatorStatus = $this->as($this->moderator())->$method(str_replace('/lists/1', "/lists/{$forModerator->id}", $uri), $body)->status();
        $this->assertSame($moderatorMayCall, $moderatorStatus === 200, "moderator got {$moderatorStatus}");

        $this->as($this->admin())->$method(str_replace('/lists/1', "/lists/{$forAdmin->id}", $uri), $body)->assertOk();
    }

    public function test_a_row_describes_the_list_its_owner_and_its_open_reports(): void
    {
        $owner = User::factory()->create(['name' => 'Maya Owner', 'username' => 'maya']);
        $list = $this->listOf($owner, ['title' => 'Best heists', 'description' => str_repeat('a', 250)], [[550], [603, 'tv']]);
        $this->reportOn($list);
        $this->reportOn($list);
        $this->reportOn($list)->update(['status' => 'dismissed']);

        $row = $this->as($this->moderator())->getJson('/api/admin/lists')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0');

        $this->assertSame([
            'id' => $list->id,
            'title' => 'Best heists',
            'description' => str_repeat('a', 200),
            'visibility' => 'public',
            'owner' => ['id' => $owner->id, 'name' => 'Maya Owner', 'username' => 'maya'],
            'items_count' => 2,
            'open_reports_count' => 2,
            'created_at' => $list->created_at->toJSON(),
            'updated_at' => $list->updated_at->toJSON(),
        ], $row);
    }

    public function test_staff_see_private_lists_and_lists_of_followers_only_owners_with_hidden_films_counted(): void
    {
        LocalMovie::factory()->hidden()->create(['tmdb_id' => 550]);
        $private = User::factory()->create(['profile_visibility' => 'private']);
        $followersOnly = User::factory()->create(['profile_visibility' => 'followers']);
        $this->listOf($private, ['title' => 'Secret', 'is_public' => false], [[550], [551]]);
        $this->listOf($followersOnly, ['title' => 'Close friends'], [[550]]);

        $response = $this->as($this->moderator())->getJson('/api/admin/lists')->assertOk();

        $this->assertEqualsCanonicalizing(['Secret', 'Close friends'], array_column($response->json('data'), 'title'));
        $this->assertSame(2, collect($response->json('data'))->firstWhere('title', 'Secret')['items_count']);
        $this->assertSame('private', collect($response->json('data'))->firstWhere('title', 'Secret')['visibility']);
    }

    public function test_lists_come_newest_first_with_ties_broken_by_id(): void
    {
        $owner = User::factory()->create();
        $old = $this->listOf($owner, ['title' => 'Old', 'created_at' => now()->subDays(3)]);
        $firstTie = $this->listOf($owner, ['title' => 'Tie A', 'created_at' => now()->subDay()]);
        $secondTie = $this->listOf($owner, ['title' => 'Tie B', 'created_at' => $firstTie->created_at]);
        $newest = $this->listOf($owner, ['title' => 'Newest', 'created_at' => now()]);

        $ids = array_column($this->as($this->admin())->getJson('/api/admin/lists')->json('data'), 'id');

        $this->assertSame([$newest->id, $secondTie->id, $firstTie->id, $old->id], $ids);
    }

    public function test_it_filters_by_text_visibility_owner_and_open_reports(): void
    {
        $maya = User::factory()->create(['name' => 'Maya Rivers', 'username' => 'maya_r']);
        $sam = User::factory()->create(['name' => 'Sam Stone', 'username' => 'samstone']);
        $this->listOf($maya, ['title' => 'Best heists']);
        $horror = $this->listOf($maya, ['title' => '100% horror', 'is_public' => false]);
        $comedy = $this->listOf($sam, ['title' => 'Comedy night']);
        $this->reportOn($comedy);
        $this->reportOn($horror)->update(['status' => 'resolved']);
        $admin = $this->admin();
        $titles = fn (string $query): array => array_column($this->as($admin)->getJson("/api/admin/lists?{$query}")->assertOk()->json('data'), 'title');

        $this->assertSame(['Best heists'], $titles('q=heist'));
        $this->assertSame(['100% horror'], $titles('q='.urlencode('100%')));
        $this->assertSame([], $titles('q='.urlencode('1_0')));
        $this->assertEqualsCanonicalizing(['Best heists', '100% horror'], $titles('q=maya_r'));
        $this->assertEqualsCanonicalizing(['Best heists', '100% horror'], $titles('q=Rivers'));
        $this->assertSame(['Comedy night'], $titles('q=samstone'));
        $this->assertSame(['100% horror'], $titles('visibility=private'));
        $this->assertEqualsCanonicalizing(['Best heists', 'Comedy night'], $titles('visibility=public'));
        $this->assertSame(['Comedy night'], $titles("owner_id={$sam->id}"));
        $this->assertSame(['Comedy night'], $titles('reported=1'));
        $this->assertEqualsCanonicalizing(['Best heists', '100% horror'], $titles('reported=0'));
        $this->assertSame(['100% horror'], $titles("visibility=private&owner_id={$maya->id}&reported=0"));
    }

    public function test_the_summary_counts_every_list_whatever_the_filters(): void
    {
        $owner = User::factory()->create();
        $this->listOf($owner, ['is_public' => false]);
        $this->listOf($owner, ['is_public' => false]);
        $reported = $this->listOf($owner);
        $this->listOf($owner);
        $this->reportOn($reported);
        $this->reportOn($reported);

        $this->as($this->moderator())->getJson('/api/admin/lists?visibility=private&per_page=1')
            ->assertOk()
            ->assertJsonPath('summary', ['total' => 4, 'public' => 2, 'private' => 2, 'reported' => 1])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_pages_follow_page_and_per_page_and_per_page_is_capped(): void
    {
        $owner = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->listOf($owner, ['created_at' => now()->subMinutes($i)]);
        }
        $admin = $this->admin();

        $this->as($admin)->getJson('/api/admin/lists?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta', ['current_page' => 3, 'last_page' => 3, 'per_page' => 2, 'total' => 5]);
        $this->as($admin)->getJson('/api/admin/lists?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->as($admin)->getJson('/api/admin/lists?visibility=followers')->assertUnprocessable()->assertJsonValidationErrors('visibility');
    }

    public function test_the_number_of_queries_does_not_grow_with_the_lists(): void
    {
        $owner = User::factory()->create();
        $admin = $this->admin();
        $this->reportOn($this->listOf($owner, films: [[550], [551]]));
        $this->as($admin)->getJson('/api/admin/lists')->assertOk();
        $count = fn (): int => $this->countQueries(fn () => $this->as($admin)->getJson('/api/admin/lists?per_page=50')->assertOk());

        $few = $count();

        for ($i = 0; $i < 6; $i++) {
            $this->reportOn($this->listOf(User::factory()->create(), films: [[550 + $i], [600 + $i]]));
        }

        $this->assertSame($few, $count());
        $this->assertCount(7, $this->as($admin)->getJson('/api/admin/lists?per_page=50')->json('data'));
    }

    public function test_the_detail_has_the_full_description_the_titles_from_the_catalogue_and_the_open_reports(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club', 'poster_path' => '/fc.jpg']);
        LocalMovie::factory()->hidden()->create(['tmdb_id' => 603, 'title' => 'The Matrix', 'poster_path' => '/m.jpg']);
        $owner = User::factory()->create(['profile_visibility' => 'private']);
        $list = $this->listOf($owner, ['description' => str_repeat('b', 300), 'is_public' => false], [[550], [603], [1399, 'tv'], [999999]]);
        $first = User::factory()->create(['name' => 'Rae Reporter', 'username' => 'rae']);
        $this->reportOn($list, $first)->update(['reason' => 'spam', 'details' => 'Ads', 'created_at' => now()->subHour()]);
        $this->reportOn($list)->update(['reason' => 'harassment', 'created_at' => now()]);
        $this->reportOn($list)->update(['status' => 'resolved']);

        $detail = $this->as($this->moderator())->getJson("/api/admin/lists/{$list->id}")->assertOk()->json('list');

        $this->assertSame(str_repeat('b', 300), $detail['description']);
        $this->assertSame('private', $detail['visibility']);
        $this->assertSame(4, $detail['items_count']);
        $this->assertSame(2, $detail['open_reports_count']);
        $this->assertSame(
            [[550, 'movie', 'Fight Club', '/fc.jpg'], [603, 'movie', 'The Matrix', '/m.jpg'], [1399, 'tv', null, null], [999999, 'movie', null, null]],
            array_map(fn (array $item): array => [$item['tmdb_id'], $item['media_type'], $item['title'], $item['poster_path']], $detail['items']),
        );
        $this->assertNotNull($detail['items'][0]['added_at']);
        $this->assertSame(['harassment', 'spam'], array_column($detail['reports'], 'reason'));
        $this->assertSame(['id' => $first->id, 'name' => 'Rae Reporter', 'username' => 'rae'], $detail['reports'][1]['reporter']);
        $this->assertSame('Ads', $detail['reports'][1]['details']);
        $this->assertNotNull($detail['reports'][1]['created_at']);
    }

    public function test_the_detail_shows_the_first_hundred_titles_in_position_order_and_never_calls_tmdb(): void
    {
        Http::preventStrayRequests();
        $list = $this->listOf(User::factory()->create());
        $rows = [];
        for ($i = 0; $i < 105; $i++) {
            $rows[] = ['movie_list_id' => $list->id, 'tmdb_id' => 1000 + $i, 'media_type' => 'movie', 'position' => 105 - $i, 'added_at' => now()];
        }
        DB::table('movie_list_items')->insert($rows);

        $detail = $this->as($this->admin())->getJson("/api/admin/lists/{$list->id}")->assertOk()->json('list');

        $this->assertSame(105, $detail['items_count']);
        $this->assertCount(100, $detail['items']);
        $this->assertSame(1104, $detail['items'][0]['tmdb_id']);
        $this->assertSame(1005, $detail['items'][99]['tmdb_id']);
    }

    public function test_an_unknown_list_is_not_found(): void
    {
        $this->as($this->admin())->getJson('/api/admin/lists/9999')->assertNotFound();
    }

    /**
     * @param  callable(): mixed  $action
     */
    private function countQueries(callable $action): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $action();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    }
}
