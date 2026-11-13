<?php

namespace Tests\Feature\Admin;

use App\Admin\Support\AdminAction;
use App\Admin\Support\AdminSubjectType;
use App\Models\AdminAuditLog;
use App\Models\MovieList;
use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
use App\MovieList\DTOs\Requests\ListFilmData;
use App\MovieList\InterfaceRepository\MovieListRepositoryInterface;
use App\MovieList\Repository\MovieListRepository;
use App\Notification\InterfaceServices\NotificationServiceInterface;
use App\Report\Support\ReportStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Mockery\MockInterface;
use RuntimeException;

/** What staff do to a list: make it private, remove a title, delete it. Rules, reports, audit entries, notices and rollbacks. */
class AdminListActionsTest extends AdminListsTestCase
{
    /** @return list<array{type: string, data: array<string, mixed>}> */
    private function noticesOf(User $member): array
    {
        return Notification::where('user_id', $member->id)->orderBy('id')->get(['type', 'data'])
            ->map(fn (Notification $notification): array => ['type' => $notification->type, 'data' => $notification->data])
            ->all();
    }

    private function owner(): User
    {
        return User::factory()->create(['name' => 'Maya Owner', 'username' => 'maya']);
    }

    private function assertReportsClosedBy(MovieList $list, User $staff, int $expected): void
    {
        $closed = Report::where('target_id', $list->id)->where('status', ReportStatus::Resolved)->get();

        $this->assertCount($expected, $closed);
        $this->assertSame([$staff->id], $closed->pluck('resolved_by')->unique()->values()->all());
        $this->assertNotNull($closed->first()?->resolved_at);
    }

    public function test_staff_make_a_public_list_private_and_the_owner_is_told(): void
    {
        $owner = $this->owner();
        $list = $this->listOf($owner, ['title' => 'Best heists'], [[550]]);
        $this->reportOn($list);
        $this->reportOn($list);
        $moderator = $this->moderator();

        $this->as($moderator)->patchJson("/api/admin/lists/{$list->id}", ['visibility' => 'private'])
            ->assertOk()
            ->assertJsonPath('list.id', $list->id)
            ->assertJsonPath('list.visibility', 'private')
            ->assertJsonPath('list.items_count', 1)
            ->assertJsonPath('list.open_reports_count', 0);

        $this->assertFalse($list->fresh()->is_public);
        $this->assertReportsClosedBy($list, $moderator, 2);
        $this->assertSame(
            [['type' => Notification::LIST_RESTRICTED, 'data' => ['list_id' => $list->id, 'list_title' => 'Best heists', 'action' => 'made_private']]],
            $this->noticesOf($owner),
        );
        $entry = AdminAuditLog::sole();
        $this->assertSame(AdminAction::ListMadePrivate, $entry->action);
        $this->assertSame(AdminSubjectType::MovieList, $entry->subject_type);
        $this->assertSame($list->id, $entry->subject_id);
        $this->assertSame('List "Best heists" by maya', $entry->subject_label);
        $this->assertSame($moderator->id, $entry->actor_id);
        $this->assertSame(['visibility' => ['from' => 'public', 'to' => 'private'], 'reports_resolved' => 2], $entry->changes);
    }

    public function test_staff_can_only_move_a_list_to_private(): void
    {
        $list = $this->listOf($this->owner(), ['is_public' => false]);
        $admin = $this->admin();

        $this->as($admin)->patchJson("/api/admin/lists/{$list->id}", ['visibility' => 'public'])->assertUnprocessable()->assertJsonValidationErrors('visibility');
        $this->as($admin)->patchJson("/api/admin/lists/{$list->id}", ['visibility' => 'followers'])->assertUnprocessable();
        $this->as($admin)->patchJson("/api/admin/lists/{$list->id}", [])->assertUnprocessable();
        $this->assertFalse($list->fresh()->is_public);
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_making_a_private_list_private_again_changes_and_records_nothing(): void
    {
        $owner = $this->owner();
        $list = $this->listOf($owner, ['is_public' => false]);
        $this->reportOn($list);

        $this->as($this->admin())->patchJson("/api/admin/lists/{$list->id}", ['visibility' => 'private'])
            ->assertOk()
            ->assertJsonPath('list.visibility', 'private')
            ->assertJsonPath('list.open_reports_count', 1);

        $this->assertSame(0, AdminAuditLog::count());
        $this->assertSame(0, Notification::count());
        $this->assertSame(1, Report::open()->count());
    }

    public function test_removing_a_title_tells_the_owner_closes_the_reports_and_is_recorded(): void
    {
        $owner = $this->owner();
        $list = $this->listOf($owner, ['title' => 'Mixed'], [[550], [603], [1399, 'tv']]);
        $this->reportOn($list);
        $admin = $this->admin();

        $this->as($admin)->deleteJson("/api/admin/lists/{$list->id}/items/1399?type=tv")
            ->assertOk()
            ->assertJsonPath('list.items_count', 2)
            ->assertJsonPath('list.open_reports_count', 0);

        $this->assertSame([[550, 'movie'], [603, 'movie']], $list->items()->get()->map(fn ($item): array => [$item->tmdb_id, $item->media_type])->all());
        $this->assertTrue($list->fresh()->is_public);
        $this->assertReportsClosedBy($list, $admin, 1);
        $this->assertSame(
            [['type' => Notification::LIST_RESTRICTED, 'data' => ['list_id' => $list->id, 'list_title' => 'Mixed', 'action' => 'item_removed', 'tmdb_id' => 1399, 'media_type' => 'tv']]],
            $this->noticesOf($owner),
        );
        $entry = AdminAuditLog::sole();
        $this->assertSame(AdminAction::ListItemRemoved, $entry->action);
        $this->assertSame('List "Mixed" by maya', $entry->subject_label);
        $this->assertSame(
            ['tmdb_id' => ['from' => 1399, 'to' => null], 'media_type' => ['from' => 'tv', 'to' => null], 'reports_resolved' => 1],
            $entry->changes,
        );
    }

    public function test_the_media_type_defaults_to_movie_and_a_title_of_the_other_type_is_not_found(): void
    {
        $list = $this->listOf($this->owner(), films: [[550], [603, 'tv']]);
        $moderator = $this->moderator();

        $this->as($moderator)->deleteJson("/api/admin/lists/{$list->id}/items/603")->assertNotFound();
        $this->as($moderator)->deleteJson("/api/admin/lists/{$list->id}/items/550?type=tv")->assertNotFound();
        $this->as($moderator)->deleteJson("/api/admin/lists/{$list->id}/items/550?type=book")->assertUnprocessable();
        $this->as($moderator)->deleteJson("/api/admin/lists/{$list->id}/items/550")->assertOk();

        $this->assertSame(1, $list->items()->count());
    }

    public function test_removing_a_title_that_is_not_there_is_a_404_that_records_nothing(): void
    {
        $owner = $this->owner();
        $list = $this->listOf($owner, films: [[550]]);
        $this->reportOn($list);

        $this->as($this->admin())->deleteJson("/api/admin/lists/{$list->id}/items/999")
            ->assertNotFound()
            ->assertJsonPath('message', 'That title is not in this list.');

        $this->assertSame(1, $list->items()->count());
        $this->assertSame(0, AdminAuditLog::count());
        $this->assertSame(0, Notification::count());
        $this->assertSame(1, Report::open()->count());
    }

    public function test_an_admin_deletes_a_list_with_its_titles_and_the_owner_is_told(): void
    {
        $owner = $this->owner();
        $list = $this->listOf($owner, ['title' => 'Best heists'], [[550], [603]]);
        $other = $this->listOf($owner, ['title' => 'Other'], [[550]]);
        $this->reportOn($list);
        $this->reportOn($list);
        $this->reportOn($other);
        $admin = $this->admin();

        $this->as($admin)->deleteJson("/api/admin/lists/{$list->id}")->assertOk()->assertJsonPath('message', 'List deleted.');

        $this->assertDatabaseMissing('movie_lists', ['id' => $list->id]);
        $this->assertSame(0, DB::table('movie_list_items')->where('movie_list_id', $list->id)->count());
        $this->assertSame(1, $other->items()->count());
        $this->assertReportsClosedBy($list, $admin, 2);
        $this->assertTrue(Report::where('target_id', $other->id)->first()->status === ReportStatus::Open);
        $this->assertSame(
            [['type' => Notification::LIST_RESTRICTED, 'data' => ['list_id' => $list->id, 'list_title' => 'Best heists', 'action' => 'deleted']]],
            $this->noticesOf($owner),
        );
        $entry = AdminAuditLog::sole();
        $this->assertSame(AdminAction::ListDeleted, $entry->action);
        $this->assertSame('List "Best heists" by maya', $entry->subject_label);
        $this->assertSame(['reports_resolved' => 2], $entry->changes);
    }

    public function test_a_moderator_cannot_delete_a_list(): void
    {
        $list = $this->listOf($this->owner());

        $this->as($this->moderator())->deleteJson("/api/admin/lists/{$list->id}")->assertForbidden();

        $this->assertDatabaseHas('movie_lists', ['id' => $list->id]);
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_an_unknown_list_is_not_found_for_every_action(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/lists/9999', ['visibility' => 'private'])->assertNotFound();
        $this->as($admin)->deleteJson('/api/admin/lists/9999/items/550')->assertNotFound();
        $this->as($admin)->deleteJson('/api/admin/lists/9999')->assertNotFound();
    }

    public function test_staff_acting_on_their_own_list_tell_nobody(): void
    {
        $admin = $this->admin();
        $list = $this->listOf($admin, films: [[550], [603]]);

        $this->as($admin)->patchJson("/api/admin/lists/{$list->id}", ['visibility' => 'private'])->assertOk();
        $this->as($admin)->deleteJson("/api/admin/lists/{$list->id}/items/550")->assertOk();
        $this->as($admin)->deleteJson("/api/admin/lists/{$list->id}")->assertOk();

        $this->assertSame(0, Notification::count());
        $this->assertSame(3, AdminAuditLog::count());
    }

    public function test_the_owner_sees_the_notice_as_from_the_watchwise_team_whatever_their_settings(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Alexandra Staffer', 'username' => 'alexstaff']);
        $owner = $this->owner();
        $list = $this->listOf($owner, ['title' => 'Best heists'], [[550], [603]]);
        $this->as($owner)->patchJson('/api/me/preferences', ['notifications' => ['review_helpful' => false, 'follow' => false]])->assertOk();

        $this->as($admin)->patchJson("/api/admin/lists/{$list->id}", ['visibility' => 'private'])->assertOk();
        $this->as($admin)->deleteJson("/api/admin/lists/{$list->id}/items/603")->assertOk();
        $this->as($admin)->deleteJson("/api/admin/lists/{$list->id}")->assertOk();

        $feed = $this->as($owner)->getJson('/api/notifications')->assertOk()->assertJsonCount(3, 'notifications');
        $this->assertStringNotContainsString('alexstaff', $feed->getContent());
        $deleted = $feed->json('notifications.0');
        $removed = $feed->json('notifications.1');
        $private = $feed->json('notifications.2');
        $team = ['id' => 0, 'username' => 'watchwise', 'name' => 'WatchWise', 'avatar' => null];

        foreach ([$deleted, $removed, $private] as $notification) {
            $this->assertSame('list_restricted', $notification['kind']);
            $this->assertSame($team, $notification['actor']);
            $this->assertSame('Best heists', $notification['target']);
            $this->assertFalse($notification['read']);
        }
        $this->assertSame('made your list private', $private['text']);
        $this->assertSame("/lists/{$list->id}", $private['to']);
        $this->assertNull($private['film']);
        $this->assertSame('removed a film from your list', $removed['text']);
        $this->assertSame(['tmdb_id' => 603, 'media_type' => 'movie'], $removed['film']);
        $this->assertSame('deleted your list', $deleted['text']);
        $this->assertSame('/lists', $deleted['to']);
        $this->as($owner)->getJson('/api/me')->assertJsonPath('counts.unread_notifications', 3);
    }

    public function test_every_action_works_on_lists_of_private_profiles_and_closes_nothing_else(): void
    {
        $owner = User::factory()->create(['profile_visibility' => 'private']);
        $list = $this->listOf($owner, films: [[550], [603]]);
        $elsewhere = $this->reportOn($this->listOf($this->owner()));
        $admin = $this->admin();

        $this->as($admin)->patchJson("/api/admin/lists/{$list->id}", ['visibility' => 'private'])->assertOk();
        $this->as($admin)->deleteJson("/api/admin/lists/{$list->id}/items/550")->assertOk();
        $this->as($admin)->deleteJson("/api/admin/lists/{$list->id}")->assertOk();

        $this->assertDatabaseMissing('movie_lists', ['id' => $list->id]);
        $this->assertSame(ReportStatus::Open, $elsewhere->fresh()->status);
    }

    public function test_a_moderator_may_restrict_the_list_of_any_owner_staff_included(): void
    {
        $adminsList = $this->listOf($this->admin(), films: [[550]]);
        $moderatorsList = $this->listOf($this->moderator(), films: [[550]]);
        $moderator = $this->moderator();

        foreach ([$adminsList, $moderatorsList] as $list) {
            $this->as($moderator)->patchJson("/api/admin/lists/{$list->id}", ['visibility' => 'private'])->assertOk();
            $this->as($moderator)->deleteJson("/api/admin/lists/{$list->id}/items/550")->assertOk();
        }
    }

    /** Makes the repository act as a second request that got in just before the one under test. */
    private function racingRepository(callable $beforeRead, string $method): void
    {
        $this->app->bind(MovieListRepositoryInterface::class, fn () => new class($beforeRead, $method) extends MovieListRepository
        {
            public function __construct(private readonly mixed $beforeRead, private readonly string $method) {}

            public function lockForUpdate(MovieList $list): ?MovieList
            {
                if ($this->method === 'lockForUpdate') {
                    ($this->beforeRead)($list);
                }

                return parent::lockForUpdate($list);
            }

            public function delete(MovieList $list): bool
            {
                if ($this->method === 'delete') {
                    ($this->beforeRead)($list);
                }

                return parent::delete($list);
            }

            public function removeItem(MovieList $list, ListFilmData $film): bool
            {
                if ($this->method === 'removeItem') {
                    ($this->beforeRead)($list);
                }

                return parent::removeItem($list, $film);
            }
        });
    }

    public function test_a_list_another_request_made_private_first_answers_with_the_current_row_and_records_nothing(): void
    {
        $owner = $this->owner();
        $list = $this->listOf($owner, films: [[550]]);
        $this->reportOn($list);
        $this->racingRepository(fn ($bound) => DB::table('movie_lists')->where('id', $bound->id)->update(['is_public' => false]), 'lockForUpdate');

        $this->as($this->admin())->patchJson("/api/admin/lists/{$list->id}", ['visibility' => 'private'])
            ->assertOk()
            ->assertJsonPath('list.visibility', 'private')
            ->assertJsonPath('list.open_reports_count', 1);

        $this->assertSame(0, AdminAuditLog::count());
        $this->assertSame(0, Notification::count());
        $this->assertSame(1, Report::open()->count());
    }

    public function test_a_list_another_request_deleted_first_is_a_404_that_records_closes_and_sends_nothing(): void
    {
        $owner = $this->owner();
        $list = $this->listOf($owner, films: [[550]]);
        $this->reportOn($list);
        $admin = $this->admin();
        $deleteFirst = fn ($bound) => DB::table('movie_lists')->where('id', $bound->id)->delete();

        $this->racingRepository($deleteFirst, 'delete');
        $this->as($admin)->deleteJson("/api/admin/lists/{$list->id}")->assertNotFound()->assertJsonPath('message', 'List not found.');

        $this->assertSame(0, AdminAuditLog::count());
        $this->assertSame(0, Notification::count());
        $this->assertSame(1, Report::open()->count());
    }

    public function test_a_list_deleted_before_it_is_made_private_or_trimmed_is_a_404_that_records_nothing(): void
    {
        $owner = $this->owner();
        $admin = $this->admin();
        $deleteFirst = fn ($bound) => DB::table('movie_lists')->where('id', $bound->id)->delete();

        $private = $this->listOf($owner, films: [[550]]);
        $this->racingRepository($deleteFirst, 'lockForUpdate');
        $this->as($admin)->patchJson("/api/admin/lists/{$private->id}", ['visibility' => 'private'])->assertNotFound();

        $trimmed = $this->listOf($owner, films: [[550]]);
        $this->racingRepository($deleteFirst, 'removeItem');
        $this->as($admin)->deleteJson("/api/admin/lists/{$trimmed->id}/items/550")->assertNotFound();

        $this->assertSame(0, AdminAuditLog::count());
        $this->assertSame(0, Notification::count());
    }

    public function test_a_failing_audit_insert_changes_and_sends_nothing(): void
    {
        $owner = $this->owner();
        $list = $this->listOf($owner, films: [[550]]);
        $this->reportOn($list);
        $admin = $this->admin();
        $this->failAuditInserts();

        $this->as($admin)->patchJson("/api/admin/lists/{$list->id}", ['visibility' => 'private'])->assertStatus(500);
        $this->as($admin)->deleteJson("/api/admin/lists/{$list->id}/items/550")->assertStatus(500);
        $this->as($admin)->deleteJson("/api/admin/lists/{$list->id}")->assertStatus(500);

        $this->assertTrue($list->fresh()->is_public);
        $this->assertSame(1, $list->items()->count());
        $this->assertSame(1, Report::open()->count());
        $this->assertSame(0, Notification::count());
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_a_failing_notification_does_not_fail_or_undo_the_action(): void
    {
        Exceptions::fake();
        $owner = $this->owner();
        $private = $this->listOf($owner, films: [[550]]);
        $trimmed = $this->listOf($owner, films: [[550]]);
        $deleted = $this->listOf($owner);
        $admin = $this->admin();
        $this->mock(NotificationServiceInterface::class, function (MockInterface $notifications): void {
            $notifications->shouldReceive('listRestricted')->times(3)->andThrow(new RuntimeException('The notification store is unavailable.'));
        });

        $this->as($admin)->patchJson("/api/admin/lists/{$private->id}", ['visibility' => 'private'])->assertOk();
        $this->as($admin)->deleteJson("/api/admin/lists/{$trimmed->id}/items/550")->assertOk();
        $this->as($admin)->deleteJson("/api/admin/lists/{$deleted->id}")->assertOk();

        $this->assertFalse($private->fresh()->is_public);
        $this->assertSame(0, $trimmed->items()->count());
        $this->assertDatabaseMissing('movie_lists', ['id' => $deleted->id]);
        $this->assertSame(3, AdminAuditLog::count());
        Exceptions::assertReportedCount(3);
    }

    public function test_a_privatised_list_leaves_other_members_views_at_once(): void
    {
        $owner = $this->owner();
        $viewer = User::factory()->create();
        $list = $this->listOf($owner, ['title' => 'Best heists'], [[550]]);
        $this->as($viewer)->getJson("/api/lists/{$list->id}")->assertOk();

        $this->as($this->admin())->patchJson("/api/admin/lists/{$list->id}", ['visibility' => 'private'])->assertOk();

        $this->as($viewer)->getJson("/api/lists/{$list->id}")->assertForbidden();
        $this->assertSame([], $this->as($viewer)->getJson('/api/lists?scope=public')->json('lists'));
        $this->as($owner)->getJson("/api/lists/{$list->id}")->assertOk()->assertJsonPath('list.is_public', false);
    }
}
