<?php

namespace Tests\Feature\User;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/** The data migration turning accounts still on the old shared social placeholder into NULL-password accounts. */
class ClearPlaceholderPasswordsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PLACEHOLDER = 'phpunit-placeholder-Xy9#only-for-tests';

    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migration = require database_path('migrations/2026_11_11_000001_clear_social_placeholder_passwords.php');
    }

    private function socialUser(string $plainPassword, array $attributes = []): User
    {
        $user = User::factory()->create(['password' => Hash::make($plainPassword), ...$attributes]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g'.$user->id, 'access_token' => 'token']);

        return $user;
    }

    private function envFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($path, $contents);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    public function test_accounts_on_the_placeholder_lose_it_and_everything_tied_to_it(): void
    {
        $onPlaceholder = $this->socialUser(self::PLACEHOLDER, [
            'password_set_without_proof_at' => now(),
            'pending_email' => 'new@example.com',
            'pending_email_nonce' => 'n',
            'deletion_requested_at' => now(),
            'deletion_nonce' => 'd',
        ]);
        // Its social login was disconnected, but it still holds the placeholder.
        $withoutSocialRow = User::factory()->create(['password' => Hash::make(self::PLACEHOLDER)]);

        $this->assertSame(2, $this->migration->clearPlaceholderPasswords(self::PLACEHOLDER));

        $onPlaceholder->refresh();
        $this->assertNull($onPlaceholder->password);
        $this->assertNull($onPlaceholder->password_set_without_proof_at);
        $this->assertNull($onPlaceholder->pending_email);
        $this->assertNull($onPlaceholder->deletion_nonce);
        $this->assertNull($withoutSocialRow->fresh()->password);
    }

    public function test_real_passwords_are_left_alone(): void
    {
        $realPassword = $this->socialUser('chosen12345');
        $unreadableHash = $this->socialUser('x');
        User::whereKey($unreadableHash->id)->update(['password' => 'not-a-bcrypt-hash']);

        $this->assertSame(0, $this->migration->clearPlaceholderPasswords(self::PLACEHOLDER));

        $this->assertTrue(Hash::check('chosen12345', $realPassword->fresh()->password));
        $this->assertSame('not-a-bcrypt-hash', $unreadableHash->fresh()->getRawOriginal('password'));
    }

    public function test_it_refuses_to_run_without_a_placeholder_while_social_accounts_have_a_password(): void
    {
        $user = $this->socialUser(self::PLACEHOLDER);

        try {
            $this->migration->clearPlaceholderPasswords(null);
            $this->fail('The migration skipped silently.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SOCIAL_PLACEHOLDER_PASSWORD', $e->getMessage());
            $this->assertStringContainsString('SKIP_SOCIAL_PLACEHOLDER_CLEANUP=true', $e->getMessage());
        }

        $this->assertNotNull($user->fresh()->password);
    }

    public function test_the_opt_out_flag_skips_it(): void
    {
        $user = $this->socialUser(self::PLACEHOLDER);

        $this->assertSame(0, $this->migration->clearPlaceholderPasswords(null, skip: true));

        $this->assertNotNull($user->fresh()->password);
    }

    public function test_an_empty_database_needs_no_placeholder(): void
    {
        User::factory()->create();
        User::factory()->withoutPassword()->create();

        $this->assertSame(0, $this->migration->clearPlaceholderPasswords(null));
    }

    public function test_settings_fall_back_to_the_env_file(): void
    {
        $file = $this->envFile("APP_NAME=WatchWise\nSOCIAL_PLACEHOLDER_PASSWORD=\"quoted value #1\"\nSKIP_SOCIAL_PLACEHOLDER_CLEANUP=true\n");

        $this->assertSame('quoted value #1', $this->migration->readSetting('SOCIAL_PLACEHOLDER_PASSWORD', null, $file));
        $this->assertSame('true', $this->migration->readSetting('SKIP_SOCIAL_PLACEHOLDER_CLEANUP', null, $file));
        $this->assertSame('from-process', $this->migration->readSetting('SOCIAL_PLACEHOLDER_PASSWORD', 'from-process', $file));
        $this->assertSame('true', $this->migration->readSetting('SKIP_SOCIAL_PLACEHOLDER_CLEANUP', true, $file));
        $this->assertNull($this->migration->readSetting('SOCIAL_PLACEHOLDER_PASSWORD', null, $this->envFile("SOCIAL_PLACEHOLDER_PASSWORD=\n")));
        $this->assertNull($this->migration->readSetting('SOCIAL_PLACEHOLDER_PASSWORD', null, sys_get_temp_dir().'/missing-'.uniqid().'.env'));
    }
}
