<?php

namespace Tests\Feature\Admin;

use App\Admin\Mail\AccountSuspendedMail;
use App\Admin\Support\AdminAction;
use App\Models\AdminAuditLog;
use App\Models\AdminUserNote;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/** Internal staff notes about a member: who may read, add and delete them, and that they never reach the member. */
class AdminUserNotesTest extends AdminTestCase
{
    private const SECRET = 'Suspected sockpuppet of another banned account.';

    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    public function test_notes_are_listed_newest_first_with_their_author_and_paged(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $old = AdminUserNote::factory()->about($member)->by($admin)->create(['body' => 'Old', 'created_at' => now()->subDays(2)]);
        $new = AdminUserNote::factory()->about($member)->by($admin)->create(['body' => 'New', 'created_at' => now()->subDay()]);
        AdminUserNote::factory()->about(User::factory()->create())->create(['body' => 'Someone else']);

        $this->as($admin)->getJson("/api/admin/users/{$member->id}/notes")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.0.body', 'New')
            ->assertJsonPath('data.0.author.id', $admin->id)
            ->assertJsonPath('data.0.author.role', 'admin')
            ->assertJsonPath('data.1.id', $old->id);
        $this->as($admin)->getJson("/api/admin/users/{$member->id}/notes?per_page=1&page=2")
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', $old->id);
    }

    public function test_a_note_whose_author_was_deleted_stays_with_no_author(): void
    {
        $author = $this->admin();
        $member = User::factory()->create();
        AdminUserNote::factory()->about($member)->by($author)->create();
        $author->delete();

        $this->as($this->admin())->getJson("/api/admin/users/{$member->id}/notes")->assertOk()->assertJsonPath('data.0.author', null);
    }

    public function test_adding_a_note_stores_it_and_audits_without_the_text(): void
    {
        $moderator = $this->moderator();
        $member = User::factory()->create(['username' => 'maya']);

        $this->as($moderator)->postJson("/api/admin/users/{$member->id}/notes", ['body' => self::SECRET])
            ->assertCreated()
            ->assertJsonPath('note.body', self::SECRET)
            ->assertJsonPath('note.author.id', $moderator->id);

        $this->assertDatabaseHas('admin_user_notes', ['user_id' => $member->id, 'author_id' => $moderator->id, 'body' => self::SECRET]);
        $entry = AdminAuditLog::query()->sole();
        $this->assertSame(AdminAction::UserNoteAdded, $entry->action);
        $this->assertSame($moderator->id, $entry->actor_id);
        $this->assertSame($member->id, $entry->subject_id);
        $this->assertSame('maya', $entry->subject_label);
        $this->assertNull($entry->changes);
        $this->assertStringNotContainsString(self::SECRET, json_encode($entry->getAttributes()));
    }

    public function test_the_body_is_required_and_at_most_a_thousand_characters(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->as($admin)->postJson("/api/admin/users/{$member->id}/notes", [])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->as($admin)->postJson("/api/admin/users/{$member->id}/notes", ['body' => str_repeat('a', 1001)])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->as($admin)->postJson("/api/admin/users/{$member->id}/notes", ['body' => str_repeat('a', 1000)])->assertCreated();
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_an_admin_deletes_any_note_and_a_moderator_only_their_own(): void
    {
        $admin = $this->admin();
        $moderator = $this->moderator();
        $member = User::factory()->create();
        $adminsNote = AdminUserNote::factory()->about($member)->by($admin)->create();
        $moderatorsNote = AdminUserNote::factory()->about($member)->by($moderator)->create();

        $this->as($moderator)->deleteJson("/api/admin/users/{$member->id}/notes/{$adminsNote->id}")->assertForbidden();
        $this->assertModelExists($adminsNote);
        $this->assertDatabaseCount('admin_audit_logs', 0);

        $this->as($moderator)->deleteJson("/api/admin/users/{$member->id}/notes/{$moderatorsNote->id}")->assertOk();
        $this->assertModelMissing($moderatorsNote);

        $this->as($admin)->deleteJson("/api/admin/users/{$member->id}/notes/{$adminsNote->id}")->assertOk();
        $this->assertModelMissing($adminsNote);

        $this->assertSame([AdminAction::UserNoteDeleted, AdminAction::UserNoteDeleted], AdminAuditLog::query()->orderBy('id')->get()->map->action->all());
        $this->assertNull(AdminAuditLog::query()->first()->changes);
    }

    public function test_a_moderator_cannot_delete_notes_of_staff_accounts_even_their_own(): void
    {
        $moderator = $this->moderator();
        $admin = $this->admin();
        $note = AdminUserNote::factory()->about($admin)->by($moderator)->create();

        $this->as($moderator)->deleteJson("/api/admin/users/{$admin->id}/notes/{$note->id}")->assertForbidden();
        $this->as($admin)->deleteJson("/api/admin/users/{$admin->id}/notes/{$note->id}")->assertForbidden();

        $this->assertModelExists($note);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_a_note_must_belong_to_the_member_in_the_url(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $note = AdminUserNote::factory()->about($other)->by($admin)->create();

        $this->as($admin)->deleteJson("/api/admin/users/{$member->id}/notes/{$note->id}")->assertNotFound();
        $this->as($admin)->deleteJson("/api/admin/users/{$member->id}/notes/999999")->assertNotFound();
        $this->assertModelExists($note);
    }

    public function test_notes_follow_the_permission_matrix(): void
    {
        $admin = $this->admin();
        $moderator = $this->moderator();
        $colleague = $this->moderator();
        $member = User::factory()->create();
        $note = AdminUserNote::factory()->about($member)->by($admin)->create();
        $body = ['body' => 'x'];

        $this->getJson("/api/admin/users/{$member->id}/notes")->assertUnauthorized();
        $this->postJson("/api/admin/users/{$member->id}/notes", $body)->assertUnauthorized();
        $this->deleteJson("/api/admin/users/{$member->id}/notes/{$note->id}")->assertUnauthorized();
        $plain = User::factory()->create();
        $this->as($plain)->getJson("/api/admin/users/{$member->id}/notes")->assertForbidden();
        $this->as($plain)->postJson("/api/admin/users/{$member->id}/notes", $body)->assertForbidden();
        $this->as($plain)->deleteJson("/api/admin/users/{$member->id}/notes/{$note->id}")->assertForbidden();

        foreach ([$admin, $colleague] as $staff) {
            $this->as($moderator)->getJson("/api/admin/users/{$staff->id}/notes")->assertForbidden();
            $this->as($moderator)->postJson("/api/admin/users/{$staff->id}/notes", $body)->assertForbidden();
        }
        $this->as($admin)->getJson("/api/admin/users/{$colleague->id}/notes")->assertOk();
        $this->as($admin)->postJson("/api/admin/users/{$colleague->id}/notes", $body)->assertCreated();

        foreach ([$admin, $moderator] as $self) {
            $this->as($self)->getJson("/api/admin/users/{$self->id}/notes")->assertForbidden();
            $this->as($self)->postJson("/api/admin/users/{$self->id}/notes", $body)->assertForbidden();
        }
        $this->assertDatabaseCount('admin_user_notes', 2);
    }

    public function test_notes_never_reach_the_member(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->as($admin)->postJson("/api/admin/users/{$member->id}/notes", ['body' => self::SECRET])->assertCreated();

        Mail::assertNothingSent();
        $this->assertSame(0, Notification::query()->where('user_id', $member->id)->count());
        foreach (['/api/me', '/api/notifications', "/api/users/{$member->username}"] as $url) {
            $this->assertStringNotContainsString(self::SECRET, $this->as($member)->getJson($url)->getContent());
        }

        $this->as($admin)->patchJson("/api/admin/users/{$member->id}", ['is_active' => false, 'reason' => 'Policy breach'])->assertOk();
        Mail::assertSent(AccountSuspendedMail::class, function (AccountSuspendedMail $mail): bool {
            [$html, $text] = (fn (): array => $this->renderForAssertions())->call($mail);

            return ! str_contains($html.$text, self::SECRET);
        });
    }

    public function test_deleting_a_member_removes_their_notes(): void
    {
        $member = User::factory()->create();
        AdminUserNote::factory()->about($member)->count(2)->create();

        $this->as($this->admin())->deleteJson("/api/admin/users/{$member->id}")->assertOk();

        $this->assertDatabaseCount('admin_user_notes', 0);
    }

    public function test_a_failing_audit_insert_rolls_back_adding_and_deleting_a_note(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();
        $note = AdminUserNote::factory()->about($member)->by($admin)->create();
        $this->failAuditInserts();

        $this->as($admin)->postJson("/api/admin/users/{$member->id}/notes", ['body' => 'New one'])->assertStatus(500);
        $this->as($admin)->deleteJson("/api/admin/users/{$member->id}/notes/{$note->id}")->assertStatus(500);

        $this->assertDatabaseCount('admin_user_notes', 1);
        $this->assertModelExists($note);
    }
}
