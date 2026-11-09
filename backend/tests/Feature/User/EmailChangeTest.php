<?php

namespace Tests\Feature\User;

use App\Models\RefreshToken;
use App\Models\User;
use App\Support\SocialPlaceholderPassword;
use App\User\Mail\ConfirmEmailChangeMail;
use App\User\Mail\EmailChangedMail;
use App\User\Mail\EmailChangeRequestedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Social\SocialTestCase;

class EmailChangeTest extends SocialTestCase
{
    private const FRONT = 'http://front.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.frontend_url' => self::FRONT]);
        Mail::fake();
    }

    /** Asks for the change and returns the confirmation link mailed to the new address. */
    private function requestChange(User $user, string $email): string
    {
        $this->as($user)->putJson('/api/me/email', ['email' => $email, 'current_password' => 'password'])->assertOk();

        $url = null;
        Mail::assertSent(ConfirmEmailChangeMail::class, function (ConfirmEmailChangeMail $mail) use ($email, &$url): bool {
            $url = $mail->url;

            return $mail->hasTo($email);
        });

        return $url;
    }

    private function redirectFor(string $status): string
    {
        return self::FRONT.'/edit-profile?email_change='.$status;
    }

    public function test_requesting_a_change_keeps_the_current_email_until_confirmed(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now()]);

        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com', 'current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'old@example.com')
            ->assertJsonPath('pending_email', 'new@example.com');

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->as($user)->getJson('/api/me')->assertJsonPath('pending_email', 'new@example.com');
        Mail::assertSent(ConfirmEmailChangeMail::class, fn (ConfirmEmailChangeMail $mail) => $mail->hasTo('new@example.com'));
        Mail::assertSent(EmailChangeRequestedMail::class, fn (EmailChangeRequestedMail $mail) => $mail->hasTo('old@example.com') && $mail->maskedNewEmail === 'n***@example.com');
    }

    public function test_the_current_password_is_required(): void
    {
        $user = User::factory()->create();

        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'Enter your current password.');
        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'Your current password is incorrect.');

        $this->assertNull($user->refresh()->pending_email);
        Mail::assertNothingSent();
    }

    public function test_accounts_without_a_password_of_their_own_skip_the_password_but_still_confirm(): void
    {
        config(['services.social_password.placeholder' => 'phpunit-placeholder-Xy9#only-for-tests']);
        $user = User::factory()->create(['password' => SocialPlaceholderPassword::forNewAccount()]);

        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('pending_email', 'new@example.com');

        $this->assertNotSame('new@example.com', $user->refresh()->email);
        Mail::assertSent(ConfirmEmailChangeMail::class);
    }

    public function test_the_new_email_must_be_different_and_free(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->as($user)->putJson('/api/me/email', ['email' => 'ME@example.com', 'current_password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->as($user)->putJson('/api/me/email', ['email' => 'taken@example.com', 'current_password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertNull($user->refresh()->pending_email);
    }

    public function test_the_link_swaps_verifies_signs_out_and_notifies_the_old_address(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now()->subYear()]);
        RefreshToken::factory()->for($user)->create();
        $url = $this->requestChange($user, 'new@example.com');

        $this->get($url)->assertRedirect($this->redirectFor('success'));

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertTrue($user->email_verified_at->equalTo(now()));
        $this->assertNull($user->pending_email);
        $this->assertSame(0, RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
        Mail::assertSent(EmailChangedMail::class, fn (EmailChangedMail $mail) => $mail->hasTo('old@example.com') && $mail->maskedNewEmail === 'n***@example.com');
    }

    public function test_a_replaced_or_cancelled_request_voids_its_link(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $first = $this->requestChange($user, 'first@example.com');
        $second = $this->requestChange($user, 'second@example.com');

        $this->get($first)->assertRedirect($this->redirectFor('invalid'));
        $this->assertSame('old@example.com', $user->refresh()->email);

        $this->as($user)->deleteJson('/api/me/email')->assertOk()->assertJsonPath('pending_email', null);

        $this->get($second)->assertRedirect($this->redirectFor('invalid'));
        $this->assertSame('old@example.com', $user->refresh()->email);
        Mail::assertNotSent(EmailChangedMail::class);
    }

    public function test_expired_and_tampered_links_redirect_without_changing_anything(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->requestChange($user, 'new@example.com');
        $params = ['id' => $user->id, 'hash' => sha1('new@example.com')];

        $this->get(URL::temporarySignedRoute('me.email.confirm', now()->subMinute(), $params))
            ->assertRedirect($this->redirectFor('expired'));
        $this->get("/api/me/email/confirm/{$user->id}/".sha1('new@example.com'))
            ->assertRedirect($this->redirectFor('invalid'));
        $this->get(URL::temporarySignedRoute('me.email.confirm', now()->addHour(), [...$params, 'hash' => sha1('other@example.com')]))
            ->assertRedirect($this->redirectFor('invalid'));

        $this->assertSame('old@example.com', $user->refresh()->email);
        $this->assertSame('new@example.com', $user->pending_email);
    }

    public function test_an_address_taken_after_the_request_is_not_applied(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = $this->requestChange($user, 'new@example.com');
        User::factory()->create(['email' => 'new@example.com']);

        $this->get($url)->assertRedirect($this->redirectFor('taken'));

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertNull($user->pending_email);
    }

    public function test_resend_mails_a_new_link_only_while_a_change_is_pending(): void
    {
        $user = User::factory()->create();

        $this->as($user)->postJson('/api/me/email/resend')->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->requestChange($user, 'new@example.com');
        $this->as($user)->postJson('/api/me/email/resend')->assertOk()->assertJsonPath('pending_email', 'new@example.com');

        Mail::assertSent(ConfirmEmailChangeMail::class, 2);
        Mail::assertSent(EmailChangeRequestedMail::class, 1);
    }

    public function test_email_change_requests_are_rate_limited_per_account(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $attempt) {
            $this->as($user)->putJson('/api/me/email', ['email' => "new{$attempt}@example.com", 'current_password' => 'password'])->assertOk();
        }

        $this->as($user)->putJson('/api/me/email', ['email' => 'new4@example.com', 'current_password' => 'password'])->assertTooManyRequests();
    }

    public function test_the_email_change_endpoints_need_a_signed_in_user(): void
    {
        $this->putJson('/api/me/email', ['email' => 'new@example.com'])->assertUnauthorized();
        $this->postJson('/api/me/email/resend')->assertUnauthorized();
        $this->deleteJson('/api/me/email')->assertUnauthorized();
    }

    public function test_the_emails_render_their_link_and_the_masked_address(): void
    {
        $confirm = new ConfirmEmailChangeMail('Jane', 'http://api.test/confirm-link', 60);
        $confirm->assertSeeInHtml('http://api.test/confirm-link', false);
        $confirm->assertSeeInText('expires in 60 minutes');

        $requested = new EmailChangeRequestedMail('Jane', 'n***@example.com');
        $requested->assertSeeInHtml('n***@example.com');
        $requested->assertSeeInText(self::FRONT.'/edit-profile');
        $requested->assertDontSeeInHtml('expires in');

        (new EmailChangedMail('Jane', 'n***@example.com'))->assertSeeInText('n***@example.com');
    }

    public function test_patch_me_accepts_the_unchanged_email_but_rejects_a_new_one(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->as($user)->patchJson('/api/me', ['name' => 'New Name', 'email' => 'me@example.com'])
            ->assertOk()
            ->assertJsonPath('user.name', 'New Name');
        $this->as($user)->patchJson('/api/me', ['email' => 'new@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame('me@example.com', $user->refresh()->email);
        Mail::assertNothingSent();
    }
}
