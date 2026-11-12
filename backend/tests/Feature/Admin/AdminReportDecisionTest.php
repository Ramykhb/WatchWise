<?php

namespace Tests\Feature\Admin;

use App\Admin\Support\AdminAction;
use App\Admin\Support\AdminSubjectType;
use App\Models\AdminAuditLog;
use App\Models\Report;
use App\Models\User;
use App\Report\Support\ReportStatus;
use App\Report\Support\ReportTargetType;

/** Staff resolve or dismiss reports; the decision is audited and touches nothing but the reports. */
class AdminReportDecisionTest extends AdminTestCase
{
    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    /**
     * Three open reports on one review and one on another.
     *
     * @return array{0: Report, 1: Report, 2: Report, 3: Report}
     */
    private function reports(): array
    {
        $review = $this->review(['is_approved' => true]);
        $other = $this->review(['is_approved' => true, 'tmdb_id' => 603]);

        return [
            Report::factory()->forReview($review)->create(),
            Report::factory()->forReview($review)->create(),
            Report::factory()->forReview($review)->create(),
            Report::factory()->forReview($other)->create(),
        ];
    }

    public function test_a_moderator_resolves_a_report_with_a_note_and_it_closes_every_open_report_on_the_content(): void
    {
        [$first, $second, $third, $other] = $this->reports();
        $third->update(['status' => ReportStatus::Dismissed, 'resolved_at' => now()->subDay()]);
        $moderator = $this->moderator();

        $this->as($moderator)->patchJson("/api/admin/reports/{$first->id}", ['status' => 'resolved', 'note' => 'Removed the link'])
            ->assertOk()
            ->assertJsonPath('reports_closed', 2)
            ->assertJsonPath('report.id', $first->id)
            ->assertJsonPath('report.status', 'resolved')
            ->assertJsonPath('report.resolution_note', 'Removed the link')
            ->assertJsonPath('report.resolved_by.id', $moderator->id);

        foreach ([$first, $second] as $report) {
            $report->refresh();
            $this->assertSame(ReportStatus::Resolved, $report->status);
            $this->assertSame($moderator->id, $report->resolved_by);
            $this->assertSame('Removed the link', $report->resolution_note);
            $this->assertNotNull($report->resolved_at);
        }

        $this->assertSame(ReportStatus::Dismissed, $third->fresh()->status);
        $this->assertSame(ReportStatus::Open, $other->fresh()->status);
    }

    public function test_an_admin_dismisses_a_report_and_leaves_the_others_open_when_asked(): void
    {
        [$first, $second] = $this->reports();
        $admin = $this->admin();

        $this->as($admin)->patchJson("/api/admin/reports/{$first->id}", ['status' => 'dismissed', 'apply_to_target' => false])
            ->assertOk()
            ->assertJsonPath('reports_closed', 1)
            ->assertJsonPath('report.status', 'dismissed')
            ->assertJsonPath('report.resolution_note', null);

        $this->assertSame(ReportStatus::Dismissed, $first->fresh()->status);
        $this->assertSame(ReportStatus::Open, $second->fresh()->status);
    }

    public function test_the_decision_does_not_touch_the_reported_content_and_tells_nobody(): void
    {
        $review = $this->review(['is_approved' => true]);
        $report = Report::factory()->forReview($review)->create();

        $this->as($this->admin())->patchJson("/api/admin/reports/{$report->id}", ['status' => 'resolved'])->assertOk();

        $review->refresh();
        $this->assertSame('Great twist.', $review->content);
        $this->assertNull($review->removed_at);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_a_closed_report_cannot_be_decided_again(): void
    {
        [$report] = $this->reports();
        $admin = $this->admin();

        $this->as($admin)->patchJson("/api/admin/reports/{$report->id}", ['status' => 'dismissed'])->assertOk();
        $this->as($admin)->patchJson("/api/admin/reports/{$report->id}", ['status' => 'resolved'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This report has already been closed.');

        $this->assertSame(ReportStatus::Dismissed, $report->fresh()->status);
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_a_report_closed_along_with_another_cannot_be_decided_on_its_own(): void
    {
        [$first, $second] = $this->reports();
        $admin = $this->admin();

        $this->as($admin)->patchJson("/api/admin/reports/{$first->id}", ['status' => 'resolved'])->assertOk();
        $this->as($admin)->patchJson("/api/admin/reports/{$second->id}", ['status' => 'dismissed'])->assertStatus(409);
    }

    public function test_the_decision_is_recorded_in_the_audit_log(): void
    {
        [$first] = $this->reports();
        $moderator = $this->moderator();
        $review = $this->review(['is_approved' => true, 'tmdb_id' => 700]);
        $linkReport = Report::factory()->forReview($review)->create(['reason' => 'Links']);

        $this->as($moderator)->patchJson("/api/admin/reports/{$first->id}", ['status' => 'resolved'])->assertOk();
        $this->as($moderator)->patchJson("/api/admin/reports/{$linkReport->id}", ['status' => 'dismissed'])->assertOk();

        [$resolved, $dismissed] = AdminAuditLog::query()->orderBy('id')->get()->all();

        $this->assertSame(AdminAction::ReportResolved, $resolved->action);
        $this->assertSame(AdminSubjectType::Report, $resolved->subject_type);
        $this->assertSame($first->id, $resolved->subject_id);
        $this->assertSame($moderator->id, $resolved->actor_id);
        $this->assertSame('moderator', $resolved->actor_role);
        $this->assertSame("Report on review #{$first->target_id}", $resolved->subject_label);
        $this->assertSame(['status' => ['from' => 'open', 'to' => 'resolved'], 'reports_closed' => 3, 'closed_report_ids' => [$first->id, $first->id + 1, $first->id + 2]], $resolved->changes);

        $this->assertSame(AdminAction::ReportDismissed, $dismissed->action);
        $this->assertSame(['status' => ['from' => 'open', 'to' => 'dismissed'], 'reports_closed' => 1, 'closed_report_ids' => [$linkReport->id]], $dismissed->changes);

        $this->as($this->admin())->getJson('/api/admin/audit-log?action=report.dismissed')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.subject.type', 'report')
            ->assertJsonPath('data.0.subject.id', $linkReport->id);
        $this->as($this->admin())->getJson("/api/admin/audit-log?subject_type=report&subject_id={$first->id}")->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_the_audit_entry_lists_at_most_100_closed_report_ids_but_counts_them_all(): void
    {
        $review = $this->review(['is_approved' => true]);
        $reports = Report::factory()->count(101)->forReview($review)->create();

        $this->as($this->admin())->patchJson("/api/admin/reports/{$reports->first()->id}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('reports_closed', 101);

        $changes = AdminAuditLog::query()->firstOrFail()->changes;
        $this->assertSame(101, $changes['reports_closed']);
        $this->assertCount(100, $changes['closed_report_ids']);
        $this->assertSame($reports->first()->id, $changes['closed_report_ids'][0]);
    }

    public function test_the_decision_is_validated(): void
    {
        [$report] = $this->reports();
        $admin = $this->admin();

        $this->as($admin)->patchJson("/api/admin/reports/{$report->id}", [])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as($admin)->patchJson("/api/admin/reports/{$report->id}", ['status' => 'open'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as($admin)->patchJson("/api/admin/reports/{$report->id}", ['status' => 'resolved', 'note' => str_repeat('a', 501)])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->as($admin)->patchJson("/api/admin/reports/{$report->id}", ['status' => 'resolved', 'apply_to_target' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors('apply_to_target');
        $this->as($admin)->patchJson("/api/admin/reports/{$report->id}", ['status' => 'resolved', 'note' => str_repeat('a', 500)])->assertOk();
        $this->as($admin)->patchJson('/api/admin/reports/9999', ['status' => 'resolved'])->assertNotFound();

        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_members_and_guests_cannot_decide(): void
    {
        [$report] = $this->reports();

        $this->patchJson("/api/admin/reports/{$report->id}", ['status' => 'resolved'])->assertUnauthorized();
        $this->as(User::factory()->create())->patchJson("/api/admin/reports/{$report->id}", ['status' => 'resolved'])->assertForbidden();

        $this->assertSame(ReportStatus::Open, $report->fresh()->status);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_a_failing_audit_insert_rolls_the_decision_back(): void
    {
        [$first, $second] = $this->reports();
        $this->failAuditInserts();

        $this->as($this->admin())->patchJson("/api/admin/reports/{$first->id}", ['status' => 'resolved', 'note' => 'Done'])->assertStatus(500);

        foreach ([$first, $second] as $report) {
            $report->refresh();
            $this->assertSame(ReportStatus::Open, $report->status);
            $this->assertNull($report->resolved_at);
            $this->assertNull($report->resolved_by);
            $this->assertNull($report->resolution_note);
        }
    }

    public function test_a_decision_on_a_report_about_removed_content_works(): void
    {
        $report = Report::factory()->create(['target_type' => ReportTargetType::Message, 'target_id' => 9999]);

        $this->as($this->admin())->patchJson("/api/admin/reports/{$report->id}", ['status' => 'dismissed'])
            ->assertOk()
            ->assertJsonPath('report.target.removed', true);
    }

    public function test_approving_a_review_dismisses_its_reports_and_rejecting_resolves_them(): void
    {
        $approved = $this->review(['is_approved' => true]);
        $rejected = $this->review(['is_approved' => true, 'tmdb_id' => 603]);
        $approvedReport = Report::factory()->forReview($approved)->create();
        $rejectedReport = Report::factory()->forReview($rejected)->create();
        $moderator = $this->moderator();

        $this->as($moderator)->patchJson("/api/admin/reviews/{$approved->id}", ['status' => 'approved'])->assertOk();
        $this->as($moderator)->patchJson("/api/admin/reviews/{$rejected->id}", ['status' => 'rejected'])->assertOk();

        $this->assertSame(ReportStatus::Dismissed, $approvedReport->fresh()->status);
        $this->assertSame(ReportStatus::Resolved, $rejectedReport->fresh()->status);

        foreach ([$approvedReport, $rejectedReport] as $report) {
            $this->assertSame($moderator->id, $report->fresh()->resolved_by);
            $this->assertNotNull($report->fresh()->resolved_at);
        }
    }

    public function test_deleting_a_review_removes_its_reports(): void
    {
        $review = $this->review(['is_approved' => true]);
        $other = $this->review(['is_approved' => true, 'tmdb_id' => 603]);
        Report::factory()->forReview($review)->create();
        $kept = Report::factory()->forReview($other)->create();

        $this->as($this->admin())->deleteJson("/api/admin/reviews/{$review->id}")->assertOk();

        $this->assertSame([$kept->id], Report::query()->pluck('id')->all());
    }
}
