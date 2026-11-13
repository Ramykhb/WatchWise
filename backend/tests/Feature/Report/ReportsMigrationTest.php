<?php

namespace Tests\Feature\Report;

use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Report\Support\ReportTargetType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The migration that replaced `review_reports` with the generic `reports` table keeps every old review report. */
class ReportsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_12_090000_create_reports_table.php');
    }

    private function review(User $author, int $tmdbId): Review
    {
        return Review::create(['user_id' => $author->id, 'tmdb_id' => $tmdbId, 'rating' => 4, 'content' => 'Great twist.', 'is_approved' => true]);
    }

    public function test_old_review_reports_move_over_with_their_ids_reasons_and_state(): void
    {
        $migration = $this->migration();
        $migration->down();
        $author = User::factory()->create();
        $first = $this->review($author, 550);
        $second = $this->review($author, 603);
        [$maya, $omar] = User::factory()->count(2)->create();

        DB::table('review_reports')->insert([
            ['id' => 7, 'review_id' => $first->id, 'user_id' => $maya->id, 'reason' => 'Spam', 'resolved_at' => null, 'created_at' => '2026-10-30 10:00:00', 'updated_at' => '2026-10-30 10:00:00'],
            ['id' => 9, 'review_id' => $first->id, 'user_id' => $omar->id, 'reason' => 'Offensive language', 'resolved_at' => '2026-11-01 09:30:00', 'created_at' => '2026-10-31 11:00:00', 'updated_at' => '2026-11-01 09:30:00'],
            ['id' => 12, 'review_id' => $second->id, 'user_id' => $maya->id, 'reason' => 'Links', 'resolved_at' => null, 'created_at' => '2026-11-02 12:00:00', 'updated_at' => '2026-11-02 12:00:00'],
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasTable('review_reports'));
        $reports = Report::query()->orderBy('id')->get();
        $this->assertSame([7, 9, 12], $reports->pluck('id')->all());
        $this->assertSame([ReportTargetType::Review], $reports->pluck('target_type')->unique()->all());
        $this->assertSame([$first->id, $first->id, $second->id], $reports->pluck('target_id')->all());
        $this->assertSame([$maya->id, $omar->id, $maya->id], $reports->pluck('reporter_id')->all());
        $this->assertSame(['Spam', 'Offensive language', 'Links'], $reports->pluck('reason')->all());
        $this->assertSame(['open', 'resolved', 'open'], $reports->map(fn (Report $report): string => $report->status->value)->all());
        $this->assertNull($reports[0]->resolved_at);
        $this->assertTrue(Carbon::parse('2026-11-01 09:30:00')->equalTo($reports[1]->resolved_at));
        $this->assertTrue(Carbon::parse('2026-10-31 11:00:00')->equalTo($reports[1]->created_at));
        $this->assertNull($reports[1]->resolved_by);
        $this->assertNull($reports[1]->details);
        $this->assertNull($reports[1]->target_label);
        $this->assertNull($reports[1]->target_excerpt);
        $this->assertNull($reports[1]->resolution_note);
    }

    public function test_the_old_flagged_status_survives_the_move(): void
    {
        $migration = $this->migration();
        $migration->down();
        $review = $this->review(User::factory()->create(), 550);
        DB::table('review_reports')->insert(['review_id' => $review->id, 'user_id' => User::factory()->create()->id, 'reason' => 'Spam', 'created_at' => now(), 'updated_at' => now()]);

        $migration->up();

        $this->assertSame(1, $review->reports()->open()->count());
    }

    public function test_rolling_back_restores_review_reports_and_drops_the_others(): void
    {
        $review = $this->review(User::factory()->create(), 550);
        $kept = Report::factory()->forReview($review)->resolved()->create(['reason' => 'Links']);
        Report::factory()->create(['target_type' => ReportTargetType::MovieList, 'target_id' => 5, 'reason' => 'spam']);

        $this->migration()->down();

        $this->assertFalse(Schema::hasTable('reports'));
        $rows = DB::table('review_reports')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($kept->id, $rows[0]->id);
        $this->assertSame($review->id, $rows[0]->review_id);
        $this->assertSame('Links', $rows[0]->reason);
        $this->assertNotNull($rows[0]->resolved_at);
        $this->migration()->up();
    }
}
