<?php

namespace Tests\Feature\Setting;

use App\Models\AppSetting;
use App\Models\User;
use App\Setting\InterfaceServices\SettingServiceInterface;
use App\Setting\Support\SettingKey;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Social\SocialTestCase;
use ValueError;

/** What the registry declares: defaults, and that a stored value that does not fit its key is read as the default. */
class SettingRegistryTest extends SocialTestCase
{
    use ChangesSettings;

    private function settings(): SettingServiceInterface
    {
        return app(SettingServiceInterface::class);
    }

    public function test_every_setting_has_its_default_while_nothing_is_stored(): void
    {
        $this->assertTrue($this->settings()->registrationOpen());
        $this->assertFalse($this->settings()->reviewsRequireApproval());
        $this->assertTrue($this->settings()->reviewAutoModeration());
        $this->assertTrue($this->settings()->roomsCreationEnabled());
        $this->assertNull($this->settings()->activeAnnouncement());
        $this->assertDatabaseCount('app_settings', 0);
    }

    public function test_a_stored_value_is_used(): void
    {
        $this->changeSettings(['registration_open' => false, 'reviews_require_approval' => true, 'review_auto_moderation' => false, 'rooms_creation_enabled' => false]);

        $this->assertFalse($this->settings()->registrationOpen());
        $this->assertTrue($this->settings()->reviewsRequireApproval());
        $this->assertFalse($this->settings()->reviewAutoModeration());
        $this->assertFalse($this->settings()->roomsCreationEnabled());
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function garbage(): array
    {
        return [
            'a switch stored as text' => ['registration_open', 'banana'],
            'a switch stored as a number' => ['registration_open', 0],
            'a switch stored as an object' => ['review_auto_moderation', ['enabled' => false]],
            'an announcement stored as a boolean' => ['announcement', true],
            'an announcement without enabled' => ['announcement', ['message' => 'Hi', 'level' => 'info']],
            'an announcement with an unknown level' => ['announcement', ['enabled' => true, 'message' => 'Hi', 'level' => 'urgent', 'expires_at' => null]],
            'an announcement with a message too long' => ['announcement', ['enabled' => true, 'message' => str_repeat('a', 301), 'level' => 'info', 'expires_at' => null]],
            'an enabled announcement without a message' => ['announcement', ['enabled' => true, 'message' => '', 'level' => 'info', 'expires_at' => null]],
            'an enabled announcement with a blank message' => ['announcement', ['enabled' => true, 'message' => "  \n ", 'level' => 'info', 'expires_at' => null]],
            'an enabled announcement with only control characters' => ['announcement', ['enabled' => true, 'message' => "\x07\x08", 'level' => 'info', 'expires_at' => null]],
            'an announcement message with HTML' => ['announcement', ['enabled' => true, 'message' => '<b>Hi</b>', 'level' => 'info', 'expires_at' => null]],
            'an announcement expiring after 2100' => ['announcement', ['enabled' => true, 'message' => 'Hi', 'level' => 'info', 'expires_at' => '2101-01-01T00:00:00Z']],
            'an announcement expiring on hour 24' => ['announcement', ['enabled' => true, 'message' => 'Hi', 'level' => 'info', 'expires_at' => '2050-01-01T24:00:00Z']],
            'an announcement with a broken expiry' => ['announcement', ['enabled' => true, 'message' => 'Hi', 'level' => 'info', 'expires_at' => 'not a date']],
        ];
    }

    #[DataProvider('garbage')]
    public function test_a_stored_value_that_does_not_fit_falls_back_to_the_default(string $key, mixed $stored): void
    {
        AppSetting::factory()->create(['key' => $key, 'value' => $stored]);
        $setting = SettingKey::from($key);

        $this->assertSame($setting->default(), $setting->resolve($stored));
        $this->assertTrue($this->settings()->registrationOpen());
        $this->assertTrue($this->settings()->reviewAutoModeration());
        $this->assertNull($this->settings()->activeAnnouncement());
    }

    public function test_a_cached_array_without_a_key_falls_back_to_the_default_of_that_key(): void
    {
        Cache::put('settings:resolved', ['registration_open' => false]);

        $this->assertFalse($this->settings()->registrationOpen());
        $this->assertFalse($this->settings()->reviewsRequireApproval());
        $this->assertTrue($this->settings()->reviewAutoModeration());
        $this->assertTrue($this->settings()->roomsCreationEnabled());
        $this->assertNull($this->settings()->activeAnnouncement());
    }

    public function test_a_normalized_announcement_normalizes_to_itself(): void
    {
        $key = SettingKey::Announcement;
        $inputs = [
            ['enabled' => true, 'message' => " Hi\r\nthere\x07 ", 'level' => 'critical', 'expires_at' => '2050-06-01T10:00:00.5+03:00'],
            ['enabled' => false, 'message' => '', 'level' => 'info', 'expires_at' => '2020-01-01T00:00Z'],
            ['enabled' => false, 'level' => 'warning'],
        ];

        foreach ($inputs as $input) {
            $once = $key->normalize($input);

            $this->assertNotNull($once);
            $this->assertSame($once, $key->normalize($once));
            $this->assertSame($once, $key->resolve($once));
        }
    }

    public function test_a_row_for_a_key_that_is_not_in_the_registry_is_ignored(): void
    {
        AppSetting::factory()->create(['key' => 'maintenance_mode', 'value' => true]);

        $this->assertCount(5, $this->settings()->entries());
        $this->assertTrue($this->settings()->registrationOpen());
    }

    public function test_the_service_refuses_an_unknown_key_or_a_value_that_does_not_fit(): void
    {
        $admin = User::factory()->admin()->create();

        try {
            $this->settings()->update($admin, ['maintenance_mode' => true]);
            $this->fail('An unknown key should be refused.');
        } catch (ValueError) {
            $this->assertDatabaseCount('app_settings', 0);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->settings()->update($admin, ['registration_open' => 'yes']);
    }

    public function test_the_announcement_is_normalized_so_equal_values_are_no_change(): void
    {
        $admin = User::factory()->admin()->create();
        $expires = now()->addDay();

        $this->settings()->update($admin, ['announcement' => $this->announcement(message: ' Hello ', expiresAt: $expires->utc()->format('Y-m-d\TH:i:s\Z'))]);
        $changes = $this->settings()->update($admin, ['announcement' => $this->announcement(message: 'Hello', expiresAt: $expires->setTimezone('Asia/Beirut')->format('c'))]);

        $this->assertSame([], $changes);
    }
}
