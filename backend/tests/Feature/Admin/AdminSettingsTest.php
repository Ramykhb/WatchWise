<?php

namespace Tests\Feature\Admin;

use App\Admin\Support\AdminAction;
use App\Admin\Support\AdminSubjectType;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Setting\ChangesSettings;

/** The staff API of the app-wide settings: admins only, strict partial updates, every real change audited. */
class AdminSettingsTest extends AdminTestCase
{
    use ChangesSettings;

    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator']);
    }

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/admin/settings')->assertUnauthorized();
        $this->patchJson('/api/admin/settings', ['registration_open' => false])->assertUnauthorized();
    }

    public function test_members_and_moderators_get_forbidden_and_change_nothing(): void
    {
        foreach ([User::factory()->create(), $this->moderator()] as $user) {
            $this->as($user)->getJson('/api/admin/settings')->assertForbidden();
            $this->as($user)->patchJson('/api/admin/settings', ['registration_open' => false])->assertForbidden();
        }

        $this->assertDatabaseCount('app_settings', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_an_admin_sees_every_setting_with_its_default_before_anything_changed(): void
    {
        $this->as($this->admin())->getJson('/api/admin/settings')
            ->assertOk()
            ->assertExactJson(['settings' => [
                'registration_open' => ['value' => true, 'updated_at' => null, 'updated_by' => null],
                'reviews_require_approval' => ['value' => false, 'updated_at' => null, 'updated_by' => null],
                'review_auto_moderation' => ['value' => true, 'updated_at' => null, 'updated_by' => null],
                'rooms_creation_enabled' => ['value' => true, 'updated_at' => null, 'updated_by' => null],
                'announcement' => ['value' => ['enabled' => false, 'message' => '', 'level' => 'info', 'expires_at' => null], 'updated_at' => null, 'updated_by' => null],
            ]]);
    }

    public function test_a_partial_update_changes_only_what_was_sent_and_returns_all_settings(): void
    {
        $admin = $this->admin();

        $response = $this->as($admin)->patchJson('/api/admin/settings', ['registration_open' => false, 'reviews_require_approval' => true])
            ->assertOk()
            ->assertJsonPath('settings.registration_open.value', false)
            ->assertJsonPath('settings.registration_open.updated_by', ['id' => $admin->id, 'name' => $admin->name, 'username' => $admin->username])
            ->assertJsonPath('settings.reviews_require_approval.value', true)
            ->assertJsonPath('settings.review_auto_moderation.value', true)
            ->assertJsonPath('settings.review_auto_moderation.updated_at', null)
            ->assertJsonPath('settings.rooms_creation_enabled.updated_by', null);

        $this->assertNotNull($response->json('settings.registration_open.updated_at'));
        $this->assertDatabaseCount('app_settings', 2);

        $this->as($admin)->patchJson('/api/admin/settings', ['review_auto_moderation' => false])->assertOk();
        $this->as($admin)->getJson('/api/admin/settings')
            ->assertJsonPath('settings.registration_open.value', false)
            ->assertJsonPath('settings.reviews_require_approval.value', true)
            ->assertJsonPath('settings.review_auto_moderation.value', false);
    }

    public function test_the_announcement_is_replaced_as_a_whole_and_its_message_is_trimmed(): void
    {
        $admin = $this->admin();
        $expires = now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z');

        $this->as($admin)->patchJson('/api/admin/settings', ['announcement' => ['enabled' => true, 'message' => '  Back at 22:00.  ', 'level' => 'critical', 'expires_at' => $expires]])
            ->assertOk()
            ->assertJsonPath('settings.announcement.value', ['enabled' => true, 'message' => 'Back at 22:00.', 'level' => 'critical', 'expires_at' => $expires]);

        $this->as($admin)->patchJson('/api/admin/settings', ['announcement' => ['enabled' => false, 'level' => 'info']])
            ->assertOk()
            ->assertJsonPath('settings.announcement.value', ['enabled' => false, 'message' => '', 'level' => 'info', 'expires_at' => null]);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidBodies(): array
    {
        $announcement = ['enabled' => true, 'message' => 'Hello', 'level' => 'info', 'expires_at' => null];

        return [
            'empty body' => [[], 'settings'],
            'unknown key' => [['maintenance_mode' => true], 'maintenance_mode'],
            'a switch as a string' => [['registration_open' => 'yes'], 'registration_open'],
            'a switch as a number' => [['reviews_require_approval' => 1], 'reviews_require_approval'],
            'a switch as null' => [['review_auto_moderation' => null], 'review_auto_moderation'],
            'a switch as an object' => [['rooms_creation_enabled' => ['value' => true]], 'rooms_creation_enabled'],
            'announcement as a boolean' => [['announcement' => true], 'announcement'],
            'announcement empty' => [['announcement' => []], 'announcement'],
            'announcement unknown field' => [['announcement' => [...$announcement, 'color' => 'red']], 'announcement'],
            'announcement without enabled' => [['announcement' => ['message' => 'Hello', 'level' => 'info']], 'announcement.enabled'],
            'announcement enabled as a string' => [['announcement' => [...$announcement, 'enabled' => 'true']], 'announcement.enabled'],
            'announcement without level' => [['announcement' => ['enabled' => false]], 'announcement.level'],
            'announcement unknown level' => [['announcement' => [...$announcement, 'level' => 'urgent']], 'announcement.level'],
            'announcement enabled without a message' => [['announcement' => ['enabled' => true, 'level' => 'info']], 'announcement.message'],
            'announcement enabled with a blank message' => [['announcement' => [...$announcement, 'message' => '   ']], 'announcement.message'],
            'announcement message too long' => [['announcement' => [...$announcement, 'message' => str_repeat('a', 301)]], 'announcement.message'],
            'announcement message not text' => [['announcement' => [...$announcement, 'message' => ['x']]], 'announcement.message'],
            'announcement expiry in the past' => [['announcement' => [...$announcement, 'expires_at' => '2020-01-01T00:00:00Z']], 'announcement.expires_at'],
            'announcement expiry without a timezone' => [['announcement' => [...$announcement, 'expires_at' => '2999-01-01T00:00:00']], 'announcement.expires_at'],
            'announcement expiry not a date' => [['announcement' => [...$announcement, 'expires_at' => 'tomorrow']], 'announcement.expires_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('invalidBodies')]
    public function test_an_invalid_body_is_a_422_that_names_the_field_and_changes_nothing(array $body, string $field): void
    {
        $this->as($this->admin())->patchJson('/api/admin/settings', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        $this->assertDatabaseCount('app_settings', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_one_invalid_setting_stops_the_valid_ones_in_the_same_request(): void
    {
        $this->as($this->admin())->patchJson('/api/admin/settings', ['registration_open' => false, 'review_auto_moderation' => 'nope'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('app_settings', 0);
    }

    public function test_an_announcement_with_a_future_expiry_with_milliseconds_is_accepted(): void
    {
        $this->as($this->admin())->patchJson('/api/admin/settings', ['announcement' => $this->announcement(expiresAt: now()->addHour()->utc()->format('Y-m-d\TH:i:s.v\Z'))])
            ->assertOk();
    }

    public function test_each_changed_setting_is_recorded_once_with_its_key_and_old_and_new_value(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/settings', ['registration_open' => false, 'review_auto_moderation' => false, 'rooms_creation_enabled' => true])->assertOk();

        $this->assertDatabaseCount('admin_audit_logs', 2);
        $entries = AdminAuditLog::query()->orderBy('id')->get();
        $this->assertSame(['registration_open', 'review_auto_moderation'], $entries->pluck('subject_label')->all());

        $entry = $entries->first();
        $this->assertSame(AdminAction::SettingsUpdated, $entry->action);
        $this->assertSame(AdminSubjectType::Setting, $entry->subject_type);
        $this->assertNull($entry->subject_id);
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame('admin', $entry->actor_role);
        $this->assertSame(['value' => ['from' => true, 'to' => false]], $entry->changes);
    }

    public function test_sending_the_current_values_writes_and_records_nothing(): void
    {
        $admin = $this->admin();
        $this->as($admin)->patchJson('/api/admin/settings', ['registration_open' => false, 'announcement' => $this->announcement()])->assertOk();
        AppSetting::query()->update(['updated_at' => now()->subDay()]);
        $before = AppSetting::query()->orderBy('key')->get()->map->getAttributes()->all();

        $this->as($admin)->patchJson('/api/admin/settings', ['registration_open' => false, 'rooms_creation_enabled' => true, 'announcement' => $this->announcement()])->assertOk();

        $this->assertSame($before, AppSetting::query()->orderBy('key')->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('admin_audit_logs', 2);
    }

    public function test_a_first_write_that_equals_the_default_creates_no_row(): void
    {
        $this->as($this->admin())->patchJson('/api/admin/settings', ['registration_open' => true, 'reviews_require_approval' => false])->assertOk();

        $this->assertDatabaseCount('app_settings', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_the_announcement_is_recorded_without_its_message_text(): void
    {
        $admin = $this->admin();
        $expires = now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z');

        $this->as($admin)->patchJson('/api/admin/settings', ['announcement' => ['enabled' => true, 'message' => 'Secret launch plans', 'level' => 'warning', 'expires_at' => $expires]])->assertOk();
        $this->as($admin)->patchJson('/api/admin/settings', ['announcement' => ['enabled' => true, 'message' => 'Other private words', 'level' => 'warning', 'expires_at' => $expires]])->assertOk();

        [$created, $reworded] = AdminAuditLog::query()->orderBy('id')->get()->all();
        $this->assertSame('announcement', $created->subject_label);
        $this->assertEquals([
            'enabled' => ['from' => false, 'to' => true],
            'level' => ['from' => 'info', 'to' => 'warning'],
            'expires_at' => ['from' => null, 'to' => $expires],
            'message' => ['changed' => true],
        ], $created->changes);
        $this->assertSame(['message' => ['changed' => true]], $reworded->changes);

        foreach ([$created, $reworded] as $entry) {
            $this->assertStringNotContainsString('Secret launch', json_encode($entry->getAttributes()));
            $this->assertStringNotContainsString('private words', json_encode($entry->getAttributes()));
        }
    }

    public function test_the_audit_log_endpoint_lists_and_filters_setting_changes(): void
    {
        $admin = $this->admin();
        $this->as($admin)->patchJson('/api/admin/settings', ['registration_open' => false])->assertOk();
        $this->as($admin)->patchJson("/api/admin/users/{$this->admin()->id}", ['is_active' => false])->assertOk();

        $this->as($admin)->getJson('/api/admin/audit-log?action=settings.updated')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', ['type' => 'setting', 'id' => null, 'label' => 'registration_open'])
            ->assertJsonPath('data.0.changes', ['value' => ['from' => true, 'to' => false]]);
        $this->as($admin)->getJson('/api/admin/audit-log?subject_type=setting')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_failing_audit_insert_rolls_the_setting_back_and_keeps_the_old_value_visible(): void
    {
        $admin = $this->admin();
        $this->as($admin)->getJson('/api/admin/settings')->assertOk();
        $this->failAuditInserts();

        $this->as($admin)->patchJson('/api/admin/settings', ['registration_open' => false, 'announcement' => $this->announcement()])->assertStatus(500);

        $this->assertDatabaseCount('app_settings', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->as($admin)->getJson('/api/admin/settings')->assertJsonPath('settings.registration_open.value', true);
        $this->getJson('/api/announcement')->assertExactJson(['announcement' => null]);
    }

    public function test_an_audit_insert_failing_on_the_second_changed_setting_rolls_back_the_first_one_too(): void
    {
        $admin = $this->admin();
        $this->as($admin)->getJson('/api/admin/settings')->assertOk();
        $this->failAuditInsertNumber(2);

        $this->as($admin)->patchJson('/api/admin/settings', ['registration_open' => false, 'rooms_creation_enabled' => false])->assertStatus(500);

        $this->assertDatabaseCount('app_settings', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->as($admin)->getJson('/api/admin/settings')
            ->assertJsonPath('settings.registration_open.value', true)
            ->assertJsonPath('settings.rooms_creation_enabled.value', true);
        $this->postJson('/api/auth/register', ['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'secret1234', 'password_confirmation' => 'secret1234'])->assertCreated();
    }

    public function test_a_later_audit_insert_that_is_not_the_failing_one_keeps_both_settings_and_both_entries(): void
    {
        $admin = $this->admin();
        $this->failAuditInsertNumber(3);

        $this->as($admin)->patchJson('/api/admin/settings', ['registration_open' => false, 'rooms_creation_enabled' => false])->assertOk();

        $this->assertDatabaseCount('app_settings', 2);
        $this->assertDatabaseCount('admin_audit_logs', 2);
    }

    public function test_a_query_string_parameter_is_not_an_unknown_setting_and_is_not_read_as_one(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/settings?utm_source=mail&page=2', ['registration_open' => false])->assertOk();
        $this->as($admin)->patchJson('/api/admin/settings?rooms_creation_enabled=false', ['review_auto_moderation' => false])->assertOk()
            ->assertJsonPath('settings.rooms_creation_enabled.value', true);
        $this->as($admin)->patchJson('/api/admin/settings?registration_open=true', [])->assertUnprocessable()->assertJsonValidationErrors(['settings']);

        $this->as($admin)->getJson('/api/admin/settings')
            ->assertJsonPath('settings.registration_open.value', false)
            ->assertJsonPath('settings.review_auto_moderation.value', false);
        $this->assertDatabaseCount('admin_audit_logs', 2);
    }

    public function test_the_response_after_an_update_reads_the_new_value_even_when_it_was_cached(): void
    {
        $admin = $this->admin();
        $this->postJson('/api/auth/register', ['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'secret1234', 'password_confirmation' => 'secret1234'])->assertCreated();

        $this->as($admin)->patchJson('/api/admin/settings', ['registration_open' => false])->assertOk();

        $this->postJson('/api/auth/register', ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret1234', 'password_confirmation' => 'secret1234'])
            ->assertForbidden();

        $this->as($admin)->patchJson('/api/admin/settings', ['registration_open' => true])->assertOk();

        $this->postJson('/api/auth/register', ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret1234', 'password_confirmation' => 'secret1234'])
            ->assertCreated();
    }

    public function test_the_editor_is_shown_as_null_once_their_account_is_deleted(): void
    {
        $editor = $this->admin();
        $this->as($editor)->patchJson('/api/admin/settings', ['registration_open' => false])->assertOk();
        $survivor = $this->admin();

        $this->as($survivor)->deleteJson("/api/admin/users/{$editor->id}")->assertOk();

        $this->as($survivor)->getJson('/api/admin/settings')
            ->assertJsonPath('settings.registration_open.value', false)
            ->assertJsonPath('settings.registration_open.updated_by', null);
        $this->assertDatabaseHas('app_settings', ['key' => 'registration_open', 'updated_by' => null]);
    }
}
