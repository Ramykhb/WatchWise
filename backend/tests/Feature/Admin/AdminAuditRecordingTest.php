<?php

namespace Tests\Feature\Admin;

use App\Admin\Support\AdminAction;
use App\Admin\Support\AdminSubjectType;
use App\Models\AdminAuditLog;
use App\Models\LocalMovie;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Storage;
use LogicException;

/** What staff do is recorded once it has gone through, and never when it was refused or failed. */
class AdminAuditRecordingTest extends AdminTestCase
{
    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    /** The only row in the log, which must hold exactly one. */
    private function onlyEntry(): AdminAuditLog
    {
        $this->assertDatabaseCount('admin_audit_logs', 1);

        return AdminAuditLog::query()->firstOrFail();
    }

    public function test_changing_a_role_is_recorded_with_the_old_and_new_role(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->as($admin)->patchJson("/api/admin/users/{$member->id}", ['role' => 'moderator'])->assertOk();

        $entry = $this->onlyEntry();
        $this->assertSame(AdminAction::UserRoleChanged, $entry->action);
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame('admin', $entry->actor_role);
        $this->assertSame(AdminSubjectType::User, $entry->subject_type);
        $this->assertSame($member->id, $entry->subject_id);
        $this->assertSame($member->username, $entry->subject_label);
        $this->assertSame(['role' => ['from' => 'user', 'to' => 'moderator']], $entry->changes);
        $this->assertNotNull($entry->created_at);
    }

    public function test_suspending_and_reinstating_are_recorded_by_a_moderator(): void
    {
        $moderator = $this->moderator();
        $member = User::factory()->create();

        $this->as($moderator)->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])->assertOk();
        $this->as($moderator)->patchJson("/api/admin/users/{$member->id}", ['is_active' => true])->assertOk();

        [$suspended, $reinstated] = AdminAuditLog::query()->orderBy('id')->get()->all();
        $this->assertSame(AdminAction::UserSuspended, $suspended->action);
        $this->assertSame(['is_active' => ['from' => true, 'to' => false]], $suspended->changes);
        $this->assertSame(AdminAction::UserReinstated, $reinstated->action);
        $this->assertSame(['is_active' => ['from' => false, 'to' => true]], $reinstated->changes);

        foreach ([$suspended, $reinstated] as $entry) {
            $this->assertSame($moderator->id, $entry->actor_id);
            $this->assertSame('moderator', $entry->actor_role);
            $this->assertSame($member->id, $entry->subject_id);
        }
    }

    public function test_one_update_changing_the_role_and_the_active_state_records_two_entries(): void
    {
        $member = User::factory()->create();

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['role' => 'moderator', 'is_active' => false])->assertOk();

        $this->assertDatabaseCount('admin_audit_logs', 2);
        $this->assertEqualsCanonicalizing(
            [AdminAction::UserRoleChanged, AdminAction::UserSuspended],
            AdminAuditLog::query()->get()->map->action->all(),
        );
    }

    public function test_an_update_that_changes_nothing_records_nothing(): void
    {
        $member = User::factory()->create();

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['role' => 'user', 'is_active' => true])->assertOk();
        $this->as($this->moderator())->patchJson("/api/admin/users/{$member->id}", ['role' => 'user'])->assertOk();

        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_deleting_a_user_keeps_a_readable_entry_without_their_email(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['email' => 'maya.private@example.com']);
        $username = $member->username;

        $this->as($admin)->deleteJson("/api/admin/users/{$member->id}")->assertOk();

        $this->assertModelMissing($member);
        $entry = $this->onlyEntry();
        $this->assertSame(AdminAction::UserDeleted, $entry->action);
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame($member->id, $entry->subject_id);
        $this->assertSame($username, $entry->subject_label);
        $this->assertNull($entry->changes);

        $this->as($admin)->getJson('/api/admin/audit-log')
            ->assertOk()
            ->assertJsonPath('data.0.subject.label', $username)
            ->assertJsonMissing(['email' => 'maya.private@example.com']);
        $this->assertStringNotContainsString('maya.private', json_encode($entry->getAttributes()));
    }

    public function test_deleting_the_actor_nulls_the_reference_but_keeps_their_entries(): void
    {
        $actor = $this->admin();
        $member = User::factory()->create();
        $this->as($actor)->patchJson("/api/admin/users/{$member->id}", ['is_active' => false])->assertOk();

        $survivor = $this->admin();
        $this->as($survivor)->deleteJson("/api/admin/users/{$actor->id}")->assertOk();

        $this->assertDatabaseCount('admin_audit_logs', 2);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.suspended', 'actor_id' => null, 'actor_role' => 'admin', 'subject_id' => $member->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.deleted', 'actor_id' => $survivor->id, 'subject_id' => $actor->id, 'subject_label' => $actor->username]);

        $this->as($survivor)->getJson('/api/admin/audit-log')
            ->assertOk()
            ->assertJsonPath('data.1.actor', ['id' => null, 'name' => null, 'username' => null, 'role' => 'admin']);
    }

    public function test_approving_and_rejecting_a_review_are_recorded_with_its_status(): void
    {
        $moderator = $this->moderator();
        $pending = $this->review();
        $standing = $this->review(['tmdb_id' => 2, 'is_approved' => true]);

        $this->as($moderator)->patchJson("/api/admin/reviews/{$pending->id}", ['status' => 'approved'])->assertOk();
        $this->as($moderator)->patchJson("/api/admin/reviews/{$standing->id}", ['status' => 'rejected'])->assertOk();

        [$approved, $rejected] = AdminAuditLog::query()->orderBy('id')->get()->all();
        $this->assertSame(AdminAction::ReviewApproved, $approved->action);
        $this->assertSame(AdminSubjectType::Review, $approved->subject_type);
        $this->assertSame($pending->id, $approved->subject_id);
        $this->assertSame('Review by '.$pending->user->username, $approved->subject_label);
        $this->assertSame(['status' => ['from' => 'pending', 'to' => 'approved'], 'reports_resolved' => 0], $approved->changes);
        $this->assertSame(AdminAction::ReviewRejected, $rejected->action);
        $this->assertSame($standing->id, $rejected->subject_id);
        $this->assertSame(['status' => ['from' => 'approved', 'to' => 'removed'], 'reports_resolved' => 0], $rejected->changes);
        $this->assertSame($moderator->id, $rejected->actor_id);
        $this->assertSame('moderator', $rejected->actor_role);
    }

    public function test_a_decision_records_how_many_open_reports_it_closed(): void
    {
        $review = $this->review();
        Report::factory()->count(2)->forReview($review)->create();
        Report::factory()->resolved()->forReview($review)->create();

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'approved'])->assertOk();

        $this->assertSame(['status' => ['from' => 'pending', 'to' => 'approved'], 'reports_resolved' => 2], $this->onlyEntry()->changes);
    }

    public function test_approving_a_flagged_review_is_recorded_even_though_its_status_stays(): void
    {
        $review = $this->review(['is_approved' => true]);
        Report::factory()->forReview($review)->create();

        $this->as($this->admin())->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'approved'])->assertOk();

        $this->assertSame(['status' => ['from' => 'approved', 'to' => 'approved'], 'reports_resolved' => 1], $this->onlyEntry()->changes);
    }

    public function test_a_review_decision_that_changes_nothing_records_nothing(): void
    {
        $admin = $this->admin();
        $approved = $this->review(['is_approved' => true]);
        $pendingThenRemoved = $this->review(['tmdb_id' => 2]);

        $this->as($admin)->patchJson("/api/admin/reviews/{$approved->id}", ['status' => 'approved'])->assertOk();
        $this->assertDatabaseCount('admin_audit_logs', 0);

        $this->as($admin)->patchJson("/api/admin/reviews/{$pendingThenRemoved->id}", ['status' => 'rejected'])->assertOk();
        $this->assertDatabaseCount('admin_audit_logs', 1);

        $this->as($admin)->patchJson("/api/admin/reviews/{$pendingThenRemoved->id}", ['status' => 'rejected'])->assertOk();
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_deleting_a_review_is_recorded_and_the_entry_outlives_it(): void
    {
        $admin = $this->admin();
        $review = $this->review(['content' => 'Secret opinion about the ending.']);

        $this->as($admin)->deleteJson("/api/admin/reviews/{$review->id}")->assertOk();

        $this->assertModelMissing($review);
        $entry = $this->onlyEntry();
        $this->assertSame(AdminAction::ReviewDeleted, $entry->action);
        $this->assertSame($review->id, $entry->subject_id);
        $this->assertSame('Review by '.$review->user->username, $entry->subject_label);
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertStringNotContainsString('Secret opinion', json_encode($entry->getAttributes()));
    }

    public function test_editing_a_movie_records_the_changed_fields_and_never_the_overview_text(): void
    {
        LocalMovie::factory()->create([
            'tmdb_id' => 550, 'title' => 'Fight Club', 'overview' => 'Original long overview.', 'runtime' => 100,
            'release_date' => '1999-10-15', 'genre_ids' => [18], 'is_hidden' => false,
        ]);
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/movies/550', [
            'title' => 'Fight Club (Local)',
            'overview' => 'Rewritten long overview.',
            'runtime' => 139,
            'release_date' => '1999-10-15',
            'genre_ids' => [18, 53],
            'is_hidden' => true,
        ])->assertOk();

        $entry = $this->onlyEntry();
        $this->assertSame(AdminAction::MovieUpdated, $entry->action);
        $this->assertSame(AdminSubjectType::Movie, $entry->subject_type);
        $this->assertSame(550, $entry->subject_id);
        $this->assertSame('Fight Club (Local)', $entry->subject_label);
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertEquals([
            'title' => ['from' => 'Fight Club', 'to' => 'Fight Club (Local)'],
            'overview' => ['changed' => true],
            'runtime' => ['from' => 100, 'to' => 139],
            'genre_ids' => ['from' => [18], 'to' => [18, 53]],
            'is_hidden' => ['from' => false, 'to' => true],
        ], $entry->changes);
        $this->assertStringNotContainsString('long overview', json_encode($entry->getAttributes()));
    }

    public function test_a_movie_edit_can_be_a_date_or_a_cleared_value(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'director' => 'David Fincher', 'release_date' => '1999-10-15']);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', ['release_date' => '1999-11-01', 'director' => null])->assertOk();

        $this->assertEquals([
            'release_date' => ['from' => '1999-10-15', 'to' => '1999-11-01'],
            'director' => ['from' => 'David Fincher', 'to' => null],
        ], $this->onlyEntry()->changes);
    }

    public function test_a_movie_update_that_changes_nothing_records_nothing(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club', 'is_featured' => true]);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', ['title' => 'Fight Club', 'is_featured' => true])->assertOk();

        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_a_stored_null_genre_list_and_an_empty_one_are_the_same(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'genre_ids' => null]);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', ['genre_ids' => []])->assertOk();

        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->assertNull(LocalMovie::where('tmdb_id', 550)->value('edited_at'));
    }

    public function test_a_content_edit_with_identical_values_does_not_mark_the_film_edited(): void
    {
        LocalMovie::factory()->create([
            'tmdb_id' => 550, 'title' => 'Fight Club', 'overview' => 'Same.', 'director' => 'David Fincher', 'runtime' => 139,
            'release_date' => '1999-10-15', 'genre_ids' => [18, 53],
        ]);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', [
            'title' => 'Fight Club', 'overview' => 'Same.', 'director' => 'David Fincher', 'runtime' => 139,
            'release_date' => '1999-10-15', 'genre_ids' => [18, 53],
        ])->assertOk()->assertJsonPath('movie.is_edited', false);

        $this->assertNull(LocalMovie::where('tmdb_id', 550)->value('edited_at'));
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_a_content_edit_that_differs_marks_the_film_edited(): void
    {
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club', 'runtime' => 139]);

        $this->as($this->admin())->patchJson('/api/admin/movies/550', ['title' => 'Fight Club', 'runtime' => 140])
            ->assertOk()
            ->assertJsonPath('movie.is_edited', true);

        $this->assertEquals(['runtime' => ['from' => 139, 'to' => 140]], $this->onlyEntry()->changes);
    }

    public function test_refused_attempts_record_nothing(): void
    {
        $admin = $this->admin();
        $moderator = $this->moderator();
        $colleague = $this->moderator();
        $member = User::factory()->create();
        $review = $this->review();

        $this->as($admin)->patchJson("/api/admin/users/{$admin->id}", ['role' => 'user'])->assertForbidden();
        $this->as($admin)->patchJson("/api/admin/users/{$admin->id}", ['is_active' => false])->assertForbidden();
        $this->as($admin)->deleteJson("/api/admin/users/{$admin->id}")->assertForbidden();
        $this->as($moderator)->patchJson("/api/admin/users/{$moderator->id}", ['is_active' => false])->assertForbidden();
        $this->as($moderator)->patchJson("/api/admin/users/{$colleague->id}", ['is_active' => false])->assertForbidden();
        $this->as($moderator)->patchJson("/api/admin/users/{$admin->id}", ['is_active' => false])->assertForbidden();
        $this->as($moderator)->patchJson("/api/admin/users/{$member->id}", ['role' => 'admin', 'is_active' => false])->assertForbidden();
        $this->as($moderator)->deleteJson("/api/admin/users/{$member->id}")->assertForbidden();
        $this->as($member)->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'approved'])->assertForbidden();
        $this->as($member)->deleteJson("/api/admin/reviews/{$review->id}")->assertForbidden();
        $this->as($moderator)->patchJson('/api/admin/movies/550', ['is_hidden' => true])->assertForbidden();

        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_invalid_and_missing_targets_record_nothing(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $review = $this->review();
        LocalMovie::factory()->create(['tmdb_id' => 550]);

        $this->as($admin)->patchJson("/api/admin/users/{$member->id}", ['role' => 'owner'])->assertUnprocessable();
        $this->as($admin)->patchJson("/api/admin/users/{$member->id}", [])->assertUnprocessable();
        $this->as($admin)->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'maybe'])->assertUnprocessable();
        $this->as($admin)->patchJson('/api/admin/movies/550', ['title' => '', 'is_hidden' => 'maybe'])->assertUnprocessable();
        $this->as($admin)->patchJson('/api/admin/users/9999', ['is_active' => false])->assertNotFound();
        $this->as($admin)->deleteJson('/api/admin/users/9999')->assertNotFound();
        $this->as($admin)->patchJson('/api/admin/reviews/9999', ['status' => 'approved'])->assertNotFound();
        $this->as($admin)->deleteJson('/api/admin/reviews/9999')->assertNotFound();
        $this->as($admin)->patchJson('/api/admin/movies/1', ['is_hidden' => true])->assertNotFound();

        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_a_failing_audit_insert_rolls_back_a_user_update(): void
    {
        $member = User::factory()->create();
        $this->failAuditInserts();

        $this->as($this->admin())->patchJson("/api/admin/users/{$member->id}", ['role' => 'moderator', 'is_active' => false])->assertStatus(500);

        $member->refresh();
        $this->assertSame('user', $member->role);
        $this->assertTrue($member->is_active);
    }

    public function test_a_failing_audit_insert_keeps_the_user_and_the_avatar_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/maya.png', 'image');
        $member = User::factory()->create();
        $member->forceFill(['avatar' => 'avatars/maya.png'])->save();
        $this->failAuditInserts();

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}")->assertStatus(500);

        $this->assertModelExists($member);
        Storage::disk('public')->assertExists('avatars/maya.png');
    }

    public function test_a_failing_audit_insert_rolls_back_review_and_movie_changes(): void
    {
        $admin = $this->admin();
        $review = $this->review(['content' => 'Keep me.']);
        Report::factory()->forReview($review)->create();
        LocalMovie::factory()->create(['tmdb_id' => 550, 'title' => 'Fight Club']);
        $this->failAuditInserts();

        $this->as($admin)->patchJson("/api/admin/reviews/{$review->id}", ['status' => 'rejected'])->assertStatus(500);
        $this->as($admin)->deleteJson("/api/admin/reviews/{$review->id}")->assertStatus(500);
        $this->as($admin)->patchJson('/api/admin/movies/550', ['title' => 'Changed'])->assertStatus(500);

        $review = Review::query()->findOrFail($review->id);
        $this->assertSame('Keep me.', $review->content);
        $this->assertNull($review->removed_at);
        $this->assertNull(Report::first()->resolved_at);
        $this->assertDatabaseHas('movies', ['tmdb_id' => 550, 'title' => 'Fight Club']);
    }

    public function test_entries_are_not_mass_assignable(): void
    {
        $entry = AdminAuditLog::factory()->create();

        $this->expectException(MassAssignmentException::class);

        $entry->update(['subject_label' => 'Changed']);
    }

    public function test_entries_cannot_be_created_by_mass_assignment(): void
    {
        $this->expectException(MassAssignmentException::class);

        AdminAuditLog::create(['actor_role' => 'admin', 'action' => AdminAction::UserDeleted, 'subject_type' => AdminSubjectType::User]);
    }

    public function test_an_entry_cannot_be_saved_with_changes(): void
    {
        $entry = AdminAuditLog::factory()->create();

        $this->expectException(LogicException::class);

        $entry->forceFill(['subject_label' => 'Changed'])->save();
    }

    public function test_an_entry_cannot_be_deleted_through_the_model(): void
    {
        $entry = AdminAuditLog::factory()->create();

        try {
            $entry->delete();
            $this->fail('Deleting an entry should throw.');
        } catch (LogicException $exception) {
            $this->assertSame('Audit log rows cannot be deleted.', $exception->getMessage());
        }

        $this->assertModelExists($entry);
    }
}
