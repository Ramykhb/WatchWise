<?php

namespace Tests\Feature\Auth;

use App\Auth\Mail\ResetPasswordMail;
use App\Models\PasswordReset;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/** Emails are stored lowercase, and every sign-in path matches whatever case is typed. */
class LowercaseEmailTest extends TestCase
{
    use RefreshDatabase;
    use SignsInWithProviders;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('s', 40), 'services.frontend_url' => 'http://front.test']);
    }

    private function register(string $email)
    {
        return $this->postJson('/api/auth/register', [
            'name' => 'Ali', 'email' => $email, 'password' => 'secret1234', 'password_confirmation' => 'secret1234',
        ]);
    }

    /** A row stored before emails were lowercased, written around the model's mutator. */
    private function legacyUser(string $email): User
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['email' => $email]);

        return $user;
    }

    private function storedEmail(User $user): ?string
    {
        return DB::table('users')->where('id', $user->id)->value('email');
    }

    public function test_a_mixed_case_registration_is_stored_lowercase(): void
    {
        Mail::fake();

        $this->register(' Ali@Example.COM ')->assertCreated();

        $this->assertSame('ali@example.com', DB::table('users')->value('email'));
    }

    public function test_an_address_registered_in_another_case_is_taken(): void
    {
        User::factory()->create(['email' => 'ali@example.com']);

        $this->register('ALI@Example.com')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);

        $this->assertSame(1, User::count());
    }

    public function test_a_legacy_mixed_case_row_still_blocks_registration(): void
    {
        $this->legacyUser('Ali@Example.com');

        $this->register('ali@example.com')->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->register('ALI@EXAMPLE.COM')->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_emails_written_through_the_model_are_lowercase(): void
    {
        $user = User::factory()->create(['email' => ' Mixed@Example.com']);
        $user->forceFill(['pending_email' => 'New@Example.com'])->save();

        $this->assertSame('mixed@example.com', $this->storedEmail($user));
        $this->assertSame('new@example.com', DB::table('users')->where('id', $user->id)->value('pending_email'));
    }

    public function test_login_works_with_any_casing_of_the_email(): void
    {
        User::factory()->create(['email' => 'ali@example.com']);

        foreach (['ali@example.com', 'ALI@example.com', ' Ali@Example.Com '] as $email) {
            $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password'])->assertOk();
        }
    }

    public function test_password_reset_works_with_any_casing_of_the_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'ali@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'ALI@Example.com'])->assertOk();

        $plain = null;
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use (&$plain): bool {
            parse_str((string) parse_url($mail->url, PHP_URL_QUERY), $query);
            $plain = $query['token'];

            return $mail->hasTo('ali@example.com') && $query['email'] === 'ali@example.com';
        });
        $this->assertSame('ali@example.com', PasswordReset::value('email'));

        $this->postJson('/api/auth/reset-password', [
            'email' => 'Ali@EXAMPLE.com', 'token' => $plain, 'password' => 'newsecret99', 'password_confirmation' => 'newsecret99',
        ])->assertOk();

        $this->assertTrue(Hash::check('newsecret99', $user->fresh()->password));
        $this->assertDatabaseCount('password_resets', 0);
    }

    private function mockGoogle(string $email): void
    {
        $this->configureSocialProviders();
        $this->mockSocialite('google', $this->socialiteUser('g1', $email, ['email_verified' => true]));
    }

    public function test_social_sign_in_with_a_mixed_case_email_links_the_lowercase_account(): void
    {
        $user = User::factory()->create(['email' => 'ali@example.com']);
        $this->mockGoogle('Ali@Example.com');

        $this->hitSocialCallback('google')->assertRedirect('http://front.test/auth/callback?status=success');

        $this->assertSame(1, User::count());
        $this->assertSame($user->id, SocialAccount::value('user_id'));
    }

    public function test_social_sign_up_with_a_mixed_case_email_stores_it_lowercase(): void
    {
        $this->mockGoogle('New.Member@Example.com');

        $this->hitSocialCallback('google')->assertRedirect('http://front.test/auth/callback?status=success');

        $this->assertSame('new.member@example.com', DB::table('users')->value('email'));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_11_000004_lowercase_emails.php');
    }

    public function test_the_migration_lowercases_stored_emails(): void
    {
        $user = $this->legacyUser(' Ali@Example.com');
        $other = User::factory()->create(['email' => 'other@example.com']);
        DB::table('users')->where('id', $other->id)->update(['pending_email' => 'New@Example.com']);
        DB::table('password_resets')->insert(['email' => 'Ali@Example.com', 'token' => 'hash', 'created_at' => now()]);

        $this->assertSame(0, $this->migration()->lowercaseEmails());

        $this->assertSame('ali@example.com', $this->storedEmail($user));
        $this->assertSame('other@example.com', $this->storedEmail($other));
        $this->assertSame('new@example.com', DB::table('users')->where('id', $other->id)->value('pending_email'));
        $this->assertSame('ali@example.com', DB::table('password_resets')->value('email'));
    }

    public function test_the_migration_changes_nothing_when_two_accounts_share_an_address_once_lowercased(): void
    {
        $upper = $this->legacyUser('Ali@Example.com');
        $lower = User::factory()->create(['email' => 'ali@example.com']);
        $mixed = $this->legacyUser('Mixed@Example.com');
        DB::table('users')->where('id', $mixed->id)->update(['pending_email' => 'ALI@example.com', 'pending_email_nonce' => 'n']);

        try {
            $this->migration()->lowercaseEmails();
            $this->fail('The migration merged two sign-in addresses.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("user {$upper->id} <Ali@Example.com>, user {$lower->id} <ali@example.com>", $e->getMessage());
            $this->assertStringContainsString('Resolve them manually', $e->getMessage());
        }

        $this->assertSame('Ali@Example.com', $this->storedEmail($upper));
        $this->assertSame('Mixed@Example.com', $this->storedEmail($mixed));
        $this->assertSame('ALI@example.com', DB::table('users')->where('id', $mixed->id)->value('pending_email'));
    }

    public function test_the_migration_clears_a_pending_email_change_to_another_accounts_address_and_carries_on(): void
    {
        User::factory()->create(['email' => 'ali@example.com']);
        $waiting = $this->legacyUser('Waiting@Example.com');
        DB::table('users')->where('id', $waiting->id)->update([
            'pending_email' => 'ALI@example.com',
            'pending_email_nonce' => 'n',
            'pending_email_authorized_at' => now(),
            'pending_email_link_sent_at' => now(),
        ]);
        $exact = User::factory()->create();
        DB::table('users')->where('id', $exact->id)->update(['pending_email' => 'ali@example.com', 'pending_email_nonce' => 'm']);
        $free = User::factory()->create();
        DB::table('users')->where('id', $free->id)->update(['pending_email' => 'Free@Example.com', 'pending_email_nonce' => 'f']);

        $this->assertSame(2, $this->migration()->lowercaseEmails());

        $cleared = DB::table('users')->where('id', $waiting->id)->first();
        $this->assertNull($cleared->pending_email);
        $this->assertNull($cleared->pending_email_nonce);
        $this->assertNull($cleared->pending_email_authorized_at);
        $this->assertNull($cleared->pending_email_link_sent_at);
        $this->assertSame('waiting@example.com', $cleared->email);
        $this->assertNull(DB::table('users')->where('id', $exact->id)->value('pending_email'));
        $this->assertSame('free@example.com', DB::table('users')->where('id', $free->id)->value('pending_email'));
        $this->assertSame('f', DB::table('users')->where('id', $free->id)->value('pending_email_nonce'));
    }

    public function test_an_email_sent_as_an_array_is_a_validation_error_not_a_server_error(): void
    {
        $this->postJson('/api/auth/login', ['email' => ['a@example.com'], 'password' => 'whatever12345'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
