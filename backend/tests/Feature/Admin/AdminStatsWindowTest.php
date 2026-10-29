<?php

namespace Tests\Feature\Admin;

use App\Models\ReviewReport;
use App\Models\User;

class AdminStatsWindowTest extends AdminTestCase
{
    public function test_it_counts_members_active_today_and_flagged_reviews(): void
    {
        User::factory()->create(['last_active_at' => now()]);
        User::factory()->create(['last_active_at' => now()->subDay()]);
        User::factory()->create(['last_active_at' => now()->subDays(5)]);
        $flagged = $this->review(['is_approved' => true]);
        ReviewReport::factory()->create(['review_id' => $flagged->id]);
        $this->review(['tmdb_id' => 2]);

        $this->as($this->admin())->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('flagged_reviews', 1)
            ->assertJsonPath('active_yesterday', 1);
    }

    public function test_active_today_counts_members_seen_today(): void
    {
        User::factory()->create(['last_active_at' => now()]);
        User::factory()->create(['last_active_at' => now()->subDays(5)]);

        // The admin's own request also stamps them as active.
        $this->as($this->admin())->getJson('/api/admin/stats')->assertOk()->assertJsonPath('active_today', 2);
    }

    public function test_a_range_sets_the_window_and_compares_it_with_the_one_before(): void
    {
        User::factory()->create(['created_at' => now()->subDays(2)]);
        User::factory()->create(['created_at' => now()->subDays(10)]);
        User::factory()->create(['created_at' => now()->subDays(12)]);
        $admin = $this->admin();

        $week = $this->as($admin)->getJson('/api/admin/stats?range=7')->assertOk();
        $week->assertJsonPath('range.name', '7')->assertJsonPath('range.days', 7)->assertJsonCount(7, 'activity');
        $this->assertSame(2, $week->json('range.new_users'));
        $this->assertSame(2, $week->json('range.previous_new_users'));

        $this->as($admin)->getJson('/api/admin/stats?range=90')->assertJsonCount(90, 'activity');
        $this->as($admin)->getJson('/api/admin/stats?range=year')->assertJsonPath('range.days', today()->dayOfYear);
    }

    public function test_the_days_parameter_still_works_and_a_bad_range_is_rejected(): void
    {
        $admin = $this->admin();

        $this->as($admin)->getJson('/api/admin/stats?days=14')->assertOk()->assertJsonCount(14, 'activity')->assertJsonPath('range.days', 14);
        $this->as($admin)->getJson('/api/admin/stats?range=45')->assertUnprocessable()->assertJsonValidationErrors('range');
    }
}
