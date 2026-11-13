<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Setting\Support\SettingKey;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Setting\ChangesSettings;

/** The announcement setting's expiry rules and its plain-text message. */
class AdminAnnouncementSettingTest extends AdminTestCase
{
    use ChangesSettings;

    public function test_disabling_with_a_past_expiry_succeeds_and_keeps_it(): void
    {
        $admin = $this->admin();
        $this->as($admin)->patchJson('/api/admin/settings', ['announcement' => $this->announcement(expiresAt: now()->addHour()->utc()->format('Y-m-d\TH:i:s\Z'))])->assertOk();

        $echoed = $this->announcement(enabled: false, expiresAt: '2020-01-01T00:00:00Z');
        $this->as($admin)->patchJson('/api/admin/settings', ['announcement' => $echoed])
            ->assertOk()
            ->assertJsonPath('settings.announcement.value', $echoed);

        $this->getJson('/api/announcement')->assertExactJson(['announcement' => null]);
    }

    public function test_enabling_with_a_past_expiry_is_refused(): void
    {
        $this->as($this->admin())->patchJson('/api/admin/settings', ['announcement' => $this->announcement(expiresAt: '2020-01-01T00:00:00Z')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['announcement.expires_at']);

        $this->assertDatabaseCount('app_settings', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function impossibleInstants(): array
    {
        return [
            'year above 2100' => ['2101-01-01T00:00:00Z'],
            'year 9999' => ['9999-12-31T23:59:59Z'],
            'year before 1970' => ['1969-12-31T23:59:59Z'],
            'a UTC year pushed above 2100 by the offset' => ['2100-12-31T23:59:59-14:00'],
            'offset +15:00' => ['2050-01-01T00:00:00+15:00'],
            'offset -14:30' => ['2050-01-01T00:00:00-14:30'],
            'offset minutes 60' => ['2050-01-01T00:00:00+01:60'],
            'hour 24' => ['2050-01-01T24:00:00Z'],
            'seconds 60' => ['2050-01-01T10:00:60Z'],
            'minute 60' => ['2050-01-01T10:60:00Z'],
            'month 13' => ['2050-13-01T10:00:00Z'],
            'day 31 of a 30-day month' => ['2050-04-31T10:00:00Z'],
            'February 30' => ['2050-02-30T10:00:00Z'],
            'no timezone' => ['2050-01-01T10:00:00'],
            'a space instead of T' => ['2050-01-01 10:00:00Z'],
            'a date only' => ['2050-01-01'],
        ];
    }

    #[DataProvider('impossibleInstants')]
    public function test_an_impossible_expiry_is_refused_whether_or_not_the_announcement_is_enabled(string $expiresAt): void
    {
        foreach ([true, false] as $enabled) {
            $this->as($this->admin())->patchJson('/api/admin/settings', ['announcement' => $this->announcement(enabled: $enabled, expiresAt: $expiresAt)])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['announcement.expires_at']);
        }

        $this->assertDatabaseCount('app_settings', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function possibleInstants(): array
    {
        return [
            'UTC' => ['2050-06-01T10:00:00Z', '2050-06-01T10:00:00Z'],
            'no seconds' => ['2050-06-01T10:00Z', '2050-06-01T10:00:00Z'],
            'milliseconds are dropped' => ['2050-06-01T10:00:00.987Z', '2050-06-01T10:00:00Z'],
            'a positive offset becomes UTC' => ['2050-06-01T10:00:00+03:00', '2050-06-01T07:00:00Z'],
            'the largest positive offset' => ['2050-06-01T10:00:00+14:00', '2050-05-31T20:00:00Z'],
            'the largest negative offset' => ['2050-06-01T10:00:00-14:00', '2050-06-02T00:00:00Z'],
            'the last allowed instant' => ['2100-12-31T23:59:59Z', '2100-12-31T23:59:59Z'],
            'a leap day' => ['2052-02-29T12:00:00Z', '2052-02-29T12:00:00Z'],
        ];
    }

    #[DataProvider('possibleInstants')]
    public function test_a_valid_expiry_is_stored_as_a_utc_instant_that_reads_back_and_shows(string $sent, string $stored): void
    {
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/settings', ['announcement' => $this->announcement(expiresAt: $sent)])
            ->assertOk()
            ->assertJsonPath('settings.announcement.value.expires_at', $stored);

        $this->assertSame($stored, AdminAuditLog::query()->firstOrFail()->changes['expires_at']['to']);
        $this->assertSame($stored, SettingKey::Announcement->resolve(['enabled' => true, 'message' => 'x', 'level' => 'info', 'expires_at' => $stored])['expires_at']);
        $this->getJson('/api/announcement')->assertOk()->assertJsonPath('announcement.expires_at', $stored);
    }

    public function test_the_message_loses_control_characters_but_keeps_line_breaks(): void
    {
        $this->as($this->admin())->patchJson('/api/admin/settings', ['announcement' => $this->announcement(message: "Down\x07 for\x00 work.\r\nBack\u{202E} soon.\rThanks\x1B")])
            ->assertOk()
            ->assertJsonPath('settings.announcement.value.message', "Down for work.\nBack soon.\nThanks");

        $this->getJson('/api/announcement')->assertJsonPath('announcement.message', "Down for work.\nBack soon.\nThanks");
    }

    public function test_an_enabled_message_of_only_control_characters_counts_as_missing(): void
    {
        $this->as($this->admin())->patchJson('/api/admin/settings', ['announcement' => $this->announcement(message: "\x07\x08\u{200F}")])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['announcement.message']);

        $this->assertDatabaseCount('app_settings', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function markup(): array
    {
        return [
            'a script tag' => ['<script>alert(1)</script>'],
            'a link' => ['See <a href="https://example.com">this</a>'],
            'a lone tag' => ['Hello<b>'],
            'a tag hidden by a control character' => ["<scr\x00ipt>alert(1)</scr\x00ipt>"],
        ];
    }

    #[DataProvider('markup')]
    public function test_a_message_with_html_tags_is_refused_with_a_clear_message(string $message): void
    {
        foreach ([true, false] as $enabled) {
            $this->as($this->admin())->patchJson('/api/admin/settings', ['announcement' => $this->announcement(enabled: $enabled, message: $message)])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['announcement.message' => 'must be plain text, without HTML tags']);
        }

        $this->assertDatabaseCount('app_settings', 0);
    }

    public function test_plain_text_that_only_looks_like_markup_is_accepted(): void
    {
        $this->as($this->admin())->patchJson('/api/admin/settings', ['announcement' => $this->announcement(message: 'Tickets: 2 < 3 & more -> soon')])
            ->assertOk()
            ->assertJsonPath('settings.announcement.value.message', 'Tickets: 2 < 3 & more -> soon');
    }

    public function test_a_message_is_limited_to_300_characters_after_cleaning(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patchJson('/api/admin/settings', ['announcement' => $this->announcement(message: str_repeat('é', 300))])->assertOk();
        $this->as($admin)->patchJson('/api/admin/settings', ['announcement' => $this->announcement(message: str_repeat('é', 301))])->assertUnprocessable();
    }
}
