<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminRatingBurstsTest extends AdminRatingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(now()->startOfSecond());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function rateMany(User $member, int $count, int $minutesAgo = 10, array $overrides = []): void
    {
        for ($film = 1; $film <= $count; $film++) {
            $this->rating(['user_id' => $member->id, 'tmdb_id' => 1000 + $film, ...$overrides], now()->subMinutes($minutesAgo));
        }
    }

    public function test_members_at_or_over_the_threshold_are_listed_with_their_count_and_window(): void
    {
        $bomber = User::factory()->create(['name' => 'Ben Bomb', 'username' => 'bomber']);
        $edge = User::factory()->create();
        $under = User::factory()->create();
        $this->rateMany($bomber, 25);
        $this->rateMany($edge, 20);
        $this->rateMany($under, 19);

        $this->as($this->admin())->getJson('/api/admin/ratings/bursts')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.user.id', $bomber->id)
            ->assertJsonPath('data.0.user.name', 'Ben Bomb')
            ->assertJsonPath('data.0.user.username', 'bomber')
            ->assertJsonPath('data.0.user.created_at', $bomber->created_at->toJSON())
            ->assertJsonPath('data.0.count', 25)
            ->assertJsonPath('data.0.window_start', now()->subHour()->toJSON())
            ->assertJsonPath('data.0.window_end', now()->toJSON())
            ->assertJsonPath('data.1.user.id', $edge->id)
            ->assertJsonPath('data.1.count', 20);
    }

    public function test_only_ratings_created_inside_the_trailing_window_count(): void
    {
        $member = User::factory()->create();
        $this->rateMany($member, 6, 30);
        $this->rateMany($member, 6, 90, ['media_type' => 'tv']);
        $this->as($this->admin());

        $this->getJson('/api/admin/ratings/bursts?min=12')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/admin/ratings/bursts?min=6')->assertJsonPath('data.0.count', 6);
        $this->getJson('/api/admin/ratings/bursts?hours=2&min=12')
            ->assertJsonPath('data.0.count', 12)
            ->assertJsonPath('data.0.window_start', now()->subHours(2)->toJSON());
    }

    public function test_changing_the_stars_of_an_old_rating_does_not_bring_it_into_the_window(): void
    {
        $member = User::factory()->create();
        $this->rateMany($member, 5, 300);
        $member->reviews()->update(['updated_at' => now()->subMinute()]);

        $this->as($this->admin())->getJson('/api/admin/ratings/bursts?min=5')->assertJsonPath('meta.total', 0);
    }

    public function test_reviews_with_text_are_not_counted(): void
    {
        $member = User::factory()->create();
        $this->rateMany($member, 4);
        $this->rateMany($member, 3, 10, ['media_type' => 'tv', 'content' => 'Text.', 'sentiment' => 'positive']);

        $this->as($this->admin())->getJson('/api/admin/ratings/bursts?min=5')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/admin/ratings/bursts?min=7')->assertJsonPath('meta.total', 0);
    }

    public function test_members_are_ordered_by_count_then_id_and_paged(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $this->rateMany($b, 6);
        $this->rateMany($a, 6);
        $this->rateMany($c, 9);
        $this->as($this->admin());

        $this->getJson('/api/admin/ratings/bursts?min=5')
            ->assertJsonPath('data.0.user.id', $c->id)
            ->assertJsonPath('data.1.user.id', $a->id)
            ->assertJsonPath('data.2.user.id', $b->id);
        $this->getJson('/api/admin/ratings/bursts?min=5&per_page=2&page=2')
            ->assertJsonPath('meta', ['current_page' => 2, 'last_page' => 2, 'per_page' => 2, 'total' => 3])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.id', $b->id);
    }

    public function test_the_parameters_are_validated(): void
    {
        $this->as($this->admin());

        foreach (['hours=0', 'hours=25', 'hours=x', 'min=4', 'min=501', 'min=x', 'page=0', 'per_page=51'] as $filter) {
            $this->getJson('/api/admin/ratings/bursts?'.$filter)->assertUnprocessable();
        }

        $this->getJson('/api/admin/ratings/bursts?hours=24&min=500&per_page=50')->assertOk();
    }

    public function test_a_rating_exactly_at_the_window_start_counts_and_one_second_earlier_does_not(): void
    {
        $inside = User::factory()->create();
        $outside = User::factory()->create();
        foreach (range(1, 5) as $film) {
            $this->rating(['user_id' => $inside->id, 'tmdb_id' => $film], now()->subHour());
            $this->rating(['user_id' => $outside->id, 'tmdb_id' => $film], now()->subHour()->subSecond());
        }

        $this->as($this->admin())->getJson('/api/admin/ratings/bursts?min=5')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user.id', $inside->id)
            ->assertJsonPath('data.0.count', 5);
    }

    public function test_a_member_with_one_rating_fewer_than_the_threshold_is_not_listed(): void
    {
        $member = User::factory()->create();
        $this->rateMany($member, 7);

        $this->as($this->admin());
        $this->getJson('/api/admin/ratings/bursts?min=7')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/ratings/bursts?min=8')->assertJsonPath('meta.total', 0);
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_members(): void
    {
        foreach (User::factory()->count(2)->create() as $member) {
            $this->rateMany($member, 5);
        }
        $this->as($this->admin())->getJson('/api/admin/ratings/bursts?min=5')->assertOk();

        DB::enableQueryLog();
        $this->getJson('/api/admin/ratings/bursts?min=5')->assertOk()->assertJsonCount(2, 'data');
        $few = count(DB::getQueryLog());

        foreach (User::factory()->count(10)->create() as $member) {
            $this->rateMany($member, 5);
        }
        DB::flushQueryLog();
        $this->getJson('/api/admin/ratings/bursts?min=5')->assertOk()->assertJsonCount(12, 'data');

        $this->assertSame($few, count(DB::getQueryLog()));
    }
}
