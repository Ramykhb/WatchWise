<?php

namespace Tests\Feature\User;

use App\Models\PasswordReset;
use App\Models\RefreshToken;
use App\Models\SocialAccount;
use App\Models\User;
use App\User\InterfaceRepository\AccountRepositoryInterface;
use App\User\Mail\AuthorizeEmailChangeMail;
use App\User\Mail\ConfirmEmailChangeMail;
use App\User\Mail\EmailChangedMail;
use App\User\Mail\EmailChangeRequestedMail;
use App\User\Repository\AccountRepository;
use Illuminate\Support\Facades\Hash;
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

        return $this->lastMailed(ConfirmEmailChangeMail::class, $email)->url;
    }

    /** An account created by social sign-in that never set a password. */
    private function socialOnlyUser(string $email = 'old@example.com'): User
    {
        return User::factory()->withoutPassword()->create(['email' => $email]);
    }

    /**
     * @template T of \Illuminate\Mail\Mailable
     *
     * @param  class-string<T>  $mailable
     * @return T
     */
    private function lastMailed(string $mailable, string $to): object
    {
        $mail = Mail::sent($mailable, fn ($mail) => $mail->hasTo($to))->last();
        $this->assertNotNull($mail, "No {$mailable} was sent to {$to}.");

        return $mail;
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
            ->assertJsonPath('pending_email', 'new@example.com')
            ->assertJsonPath('pending_email_awaiting_current', false);

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->as($user)->getJson('/api/me')->assertJsonPath('pending_email', 'new@example.com');
        Mail::assertSent(ConfirmEmailChangeMail::class, fn (ConfirmEmailChangeMail $mail) => $mail->hasTo('new@example.com'));
        Mail::assertSent(EmailChangeRequestedMail::class, fn (EmailChangeRequestedMail $mail) => $mail->hasTo('old@example.com') && $mail->maskedNewEmail === 'n***@example.com');
        Mail::assertNotSent(AuthorizeEmailChangeMail::class);
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

    public function test_an_account_without_a_password_must_approve_from_its_current_address_first(): void
    {
        $user = $this->socialOnlyUser();

        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('pending_email', 'new@example.com')
            ->assertJsonPath('pending_email_awaiting_current', true);

        $this->assertSame('old@example.com', $user->refresh()->email);
        Mail::assertNotSent(ConfirmEmailChangeMail::class);
        Mail::assertSent(AuthorizeEmailChangeMail::class, fn (AuthorizeEmailChangeMail $mail) => $mail->hasTo('old@example.com') && $mail->maskedNewEmail === 'n***@example.com');
        Mail::assertSent(AuthorizeEmailChangeMail::class, 1);
        $this->as($user)->getJson('/api/me')->assertJsonPath('pending_email_awaiting_current', true);
    }

    public function test_an_account_without_a_password_completes_the_change_through_both_addresses(): void
    {
        $this->freezeSecond();
        $user = $this->socialOnlyUser();
        RefreshToken::factory()->for($user)->create();
        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com'])->assertOk();
        $authorizeUrl = $this->lastMailed(AuthorizeEmailChangeMail::class, 'old@example.com')->url;

        $this->get($authorizeUrl)->assertRedirect($this->redirectFor('check-new-inbox'));

        $this->as($user)->getJson('/api/me')->assertJsonPath('pending_email_awaiting_current', false);
        $confirmUrl = $this->lastMailed(ConfirmEmailChangeMail::class, 'new@example.com')->url;

        // Opening the approval link again changes nothing and mails nothing new.
        $this->get($authorizeUrl)->assertRedirect($this->redirectFor('check-new-inbox'));
        Mail::assertSent(ConfirmEmailChangeMail::class, 1);

        $this->get($confirmUrl)->assertRedirect($this->redirectFor('success'));

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertTrue($user->email_verified_at->equalTo(now()));
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_authorized_at);
        $this->assertSame(0, RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
        Mail::assertSent(EmailChangedMail::class, fn (EmailChangedMail $mail) => $mail->hasTo('old@example.com') && $mail->maskedNewEmail === 'n***@example.com');
    }

    /** Sets a password in Settings, as a stolen session on a social-only account could (no current password needed). */
    private function setFirstPassword(User $user, string $password): void
    {
        $this->as($user)->putJson('/api/me/password/set', ['password' => $password, 'password_confirmation' => $password])->assertOk();
    }

    private function resetPasswordByEmail(string $email, string $password): void
    {
        $record = new PasswordReset(['email' => $email, 'token' => Hash::make('goodtoken')]);
        $record->created_at = now();
        $record->save();

        $this->postJson('/api/auth/reset-password', ['email' => $email, 'token' => 'goodtoken', 'password' => $password, 'password_confirmation' => $password])
            ->assertOk();
    }

    public function test_a_first_password_set_in_settings_still_needs_the_current_address_to_approve(): void
    {
        $user = $this->socialOnlyUser();
        $this->setFirstPassword($user, 'chosen12345');

        $this->as($user)->putJson('/api/me/email', ['email' => 'attacker@example.com', 'current_password' => 'chosen12345'])
            ->assertOk()
            ->assertJsonPath('has_password', true)
            ->assertJsonPath('pending_email_awaiting_current', true);

        Mail::assertNotSent(ConfirmEmailChangeMail::class);
        Mail::assertNotSent(EmailChangeRequestedMail::class);
        Mail::assertSent(AuthorizeEmailChangeMail::class, fn (AuthorizeEmailChangeMail $mail) => $mail->hasTo('old@example.com'));
    }

    public function test_a_first_password_still_has_to_be_typed_for_an_email_change(): void
    {
        $user = $this->socialOnlyUser();
        $this->setFirstPassword($user, 'chosen12345');

        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'Your current password is incorrect.');
        Mail::assertNotSent(AuthorizeEmailChangeMail::class);
        Mail::assertNotSent(ConfirmEmailChangeMail::class);
    }

    public function test_an_ordinary_password_change_keeps_the_first_password_unproven(): void
    {
        $user = $this->socialOnlyUser();
        $this->setFirstPassword($user, 'chosen12345');
        $this->as($user)->putJson('/api/me/password', ['current_password' => 'chosen12345', 'password' => 'second12345', 'password_confirmation' => 'second12345'])
            ->assertOk();

        $this->assertNotNull($user->refresh()->password_set_without_proof_at);
        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com', 'current_password' => 'second12345'])
            ->assertOk()
            ->assertJsonPath('pending_email_awaiting_current', true);
        Mail::assertNotSent(ConfirmEmailChangeMail::class);
    }

    public function test_disconnecting_the_social_login_keeps_the_first_password_unproven(): void
    {
        $user = $this->socialOnlyUser();
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g1', 'access_token' => 'token']);
        $this->setFirstPassword($user, 'chosen12345');

        $this->as($user)->deleteJson('/api/me/social/google')->assertOk();

        $this->assertSame(0, $user->socialAccounts()->count());
        $this->assertNotNull($user->refresh()->password_set_without_proof_at);
        $this->as($user)->putJson('/api/me/email', ['email' => 'attacker@example.com', 'current_password' => 'chosen12345'])
            ->assertOk()
            ->assertJsonPath('pending_email_awaiting_current', true);
        Mail::assertNotSent(ConfirmEmailChangeMail::class);
        Mail::assertSent(AuthorizeEmailChangeMail::class, fn (AuthorizeEmailChangeMail $mail) => $mail->hasTo('old@example.com'));
    }

    public function test_a_password_reset_through_email_makes_the_password_count_as_proof(): void
    {
        $user = $this->socialOnlyUser();
        $this->setFirstPassword($user, 'chosen12345');
        $this->resetPasswordByEmail('old@example.com', 'reset12345');

        $this->assertNull($user->refresh()->password_set_without_proof_at);
        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com', 'current_password' => 'reset12345'])
            ->assertOk()
            ->assertJsonPath('pending_email_awaiting_current', false);
        Mail::assertSent(ConfirmEmailChangeMail::class, fn (ConfirmEmailChangeMail $mail) => $mail->hasTo('new@example.com'));
        Mail::assertNotSent(AuthorizeEmailChangeMail::class);
    }

    public function test_a_change_completed_through_both_addresses_makes_the_password_count_as_proof(): void
    {
        $user = $this->socialOnlyUser();
        $this->setFirstPassword($user, 'chosen12345');
        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com', 'current_password' => 'chosen12345'])->assertOk();
        $this->get($this->lastMailed(AuthorizeEmailChangeMail::class, 'old@example.com')->url)->assertRedirect($this->redirectFor('check-new-inbox'));

        $this->get($this->lastMailed(ConfirmEmailChangeMail::class, 'new@example.com')->url)->assertRedirect($this->redirectFor('success'));

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->password_set_without_proof_at);
    }

    public function test_an_account_that_registered_with_a_password_is_never_marked(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->as($user)->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'second12345', 'password_confirmation' => 'second12345'])
            ->assertOk();

        $this->assertNull($user->refresh()->password_set_without_proof_at);
        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com', 'current_password' => 'second12345'])
            ->assertOk()
            ->assertJsonPath('pending_email_awaiting_current', false);
        Mail::assertSent(ConfirmEmailChangeMail::class, fn (ConfirmEmailChangeMail $mail) => $mail->hasTo('new@example.com'));
    }

    public function test_the_new_address_link_is_refused_until_the_current_address_approves(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = $this->requestChange($user, 'new@example.com');
        // The same link, but as if the current address had not approved yet (as for an account without a password).
        $user->forceFill(['pending_email_authorized_at' => null])->save();

        $this->get($url)->assertRedirect($this->redirectFor('invalid'));

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('new@example.com', $user->pending_email);
        Mail::assertNotSent(EmailChangedMail::class);
    }

    /** Runs $concurrently on the row right before the given compare-and-set write, as a parallel request would. */
    private function interleaveBefore(string $write, callable $concurrently): void
    {
        $this->app->bind(AccountRepositoryInterface::class, fn () => new class($write, $concurrently) extends AccountRepository
        {
            public function __construct(private string $write, private $concurrently) {}

            public function authorizePendingEmail(User $user, string $nonce): bool
            {
                if ($this->write === 'authorize') {
                    ($this->concurrently)(User::find($user->id));
                }

                return parent::authorizePendingEmail($user, $nonce);
            }

            public function applyPendingEmail(User $user, string $nonce): bool
            {
                if ($this->write === 'apply') {
                    ($this->concurrently)(User::find($user->id));
                }

                return parent::applyPendingEmail($user, $nonce);
            }
        });
    }

    public function test_a_request_replaced_while_its_approval_link_is_opened_is_not_approved(): void
    {
        $user = $this->socialOnlyUser();
        $this->as($user)->putJson('/api/me/email', ['email' => 'first@example.com'])->assertOk();
        $url = $this->lastMailed(AuthorizeEmailChangeMail::class, 'old@example.com')->url;
        $this->interleaveBefore('authorize', fn (User $row) => $row->forceFill(['pending_email' => 'second@example.com', 'pending_email_nonce' => 'newer'])->save());

        $this->get($url)->assertRedirect($this->redirectFor('invalid'));

        $this->assertNull($user->refresh()->pending_email_authorized_at);
        Mail::assertNotSent(ConfirmEmailChangeMail::class);
    }

    public function test_two_clicks_on_the_approval_link_at_once_mail_the_new_address_once(): void
    {
        $user = $this->socialOnlyUser();
        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com'])->assertOk();
        $url = $this->lastMailed(AuthorizeEmailChangeMail::class, 'old@example.com')->url;
        // The other click wins the race and approves first.
        $this->interleaveBefore('authorize', fn (User $row) => $row->forceFill(['pending_email_authorized_at' => now()])->save());

        $this->get($url)->assertRedirect($this->redirectFor('check-new-inbox'));

        Mail::assertNotSent(ConfirmEmailChangeMail::class);
    }

    public function test_a_request_replaced_while_its_confirmation_link_is_opened_is_not_applied(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = $this->requestChange($user, 'first@example.com');
        $this->interleaveBefore('apply', fn (User $row) => $row->forceFill(['pending_email' => 'second@example.com', 'pending_email_nonce' => 'newer'])->save());

        $this->get($url)->assertRedirect($this->redirectFor('invalid'));

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('second@example.com', $user->pending_email);
        Mail::assertNotSent(EmailChangedMail::class);
    }

    public function test_an_address_taken_before_the_approval_is_not_mailed(): void
    {
        $user = $this->socialOnlyUser();
        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com'])->assertOk();
        User::factory()->create(['email' => 'new@example.com']);

        $this->get($this->lastMailed(AuthorizeEmailChangeMail::class, 'old@example.com')->url)
            ->assertRedirect($this->redirectFor('taken'));

        $this->assertNull($user->refresh()->pending_email);
        Mail::assertNotSent(ConfirmEmailChangeMail::class);
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

        $this->as($user)->deleteJson('/api/me/email')
            ->assertOk()
            ->assertJsonPath('pending_email', null)
            ->assertJsonPath('pending_email_awaiting_current', false);

        $user->refresh();
        $this->assertNull($user->pending_email_nonce);
        $this->assertNull($user->pending_email_authorized_at);
        $this->get($second)->assertRedirect($this->redirectFor('invalid'));
        $this->assertSame('old@example.com', $user->refresh()->email);
        Mail::assertNotSent(EmailChangedMail::class);
    }

    public function test_asking_for_the_same_address_again_never_revives_an_older_link(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $firstForA = $this->requestChange($user, 'a@example.com');
        $this->requestChange($user, 'b@example.com');
        $secondForA = $this->requestChange($user, 'a@example.com');

        $this->get($firstForA)->assertRedirect($this->redirectFor('invalid'));
        $this->assertSame('old@example.com', $user->refresh()->email);

        $this->get($secondForA)->assertRedirect($this->redirectFor('success'));
        $this->assertSame('a@example.com', $user->refresh()->email);
    }

    public function test_changing_the_password_voids_the_link_and_clears_the_pending_change(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = $this->requestChange($user, 'attacker@example.com');

        $this->as($user)->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'newsecret99', 'password_confirmation' => 'newsecret99'])
            ->assertOk();

        $user->refresh();
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_nonce);
        $this->get($url)->assertRedirect($this->redirectFor('invalid'));
        $this->assertSame('old@example.com', $user->refresh()->email);
        Mail::assertNotSent(EmailChangedMail::class);
    }

    public function test_resetting_the_password_voids_the_link_and_clears_the_pending_change(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = $this->requestChange($user, 'attacker@example.com');

        $this->resetPasswordByEmail('old@example.com', 'newsecret99');

        $this->assertNull($user->refresh()->pending_email);
        $this->get($url)->assertRedirect($this->redirectFor('invalid'));
        $this->assertSame('old@example.com', $user->refresh()->email);
    }

    public function test_the_link_is_bound_to_the_password_hash_even_if_the_pending_change_survives(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = $this->requestChange($user, 'attacker@example.com');
        // A password written by some other path that does not clear the pending change.
        $user->forceFill(['password' => 'somethingelse1'])->save();

        $this->get($url)->assertRedirect($this->redirectFor('invalid'));

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('attacker@example.com', $user->pending_email);
    }

    public function test_the_cancel_link_drops_the_pending_change_and_voids_its_links(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $confirmUrl = $this->requestChange($user, 'attacker@example.com');
        $cancelUrl = $this->lastMailed(EmailChangeRequestedMail::class, 'old@example.com')->cancelUrl;

        $this->get($cancelUrl)->assertRedirect($this->redirectFor('cancelled'));

        $user->refresh();
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_nonce);
        $this->get($confirmUrl)->assertRedirect($this->redirectFor('invalid'));
        // Nothing is pending any more, so the same link is now a harmless no-op.
        $this->get($cancelUrl)->assertRedirect($this->redirectFor('expired'));
        $this->assertSame('old@example.com', $user->refresh()->email);
    }

    public function test_the_cancel_link_in_the_approval_mail_voids_the_approval_link(): void
    {
        $user = $this->socialOnlyUser();
        $this->as($user)->putJson('/api/me/email', ['email' => 'attacker@example.com'])->assertOk();
        $mail = $this->lastMailed(AuthorizeEmailChangeMail::class, 'old@example.com');

        $this->get($mail->cancelUrl)->assertRedirect($this->redirectFor('cancelled'));

        $this->assertNull($user->refresh()->pending_email);
        $this->get($mail->url)->assertRedirect($this->redirectFor('invalid'));
        Mail::assertNotSent(ConfirmEmailChangeMail::class);
    }

    public function test_a_cancel_link_only_cancels_the_request_it_was_mailed_for(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->requestChange($user, 'first@example.com');
        $staleCancelUrl = $this->lastMailed(EmailChangeRequestedMail::class, 'old@example.com')->cancelUrl;
        $this->requestChange($user, 'second@example.com');

        $this->get($staleCancelUrl)->assertRedirect($this->redirectFor('invalid'));
        $this->get(URL::temporarySignedRoute('me.email.cancel', now()->addHour(), ['id' => $user->id, 'hash' => str_repeat('0', 64)]))
            ->assertRedirect($this->redirectFor('invalid'));

        $this->assertSame('second@example.com', $user->refresh()->pending_email);
    }

    public function test_expired_and_tampered_links_redirect_without_changing_anything(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->requestChange($user, 'new@example.com');
        $params = ['id' => $user->id, 'hash' => str_repeat('a', 64)];

        foreach (['confirm', 'authorize', 'cancel'] as $link) {
            $this->get(URL::temporarySignedRoute("me.email.{$link}", now()->subMinute(), $params))
                ->assertRedirect($this->redirectFor('expired'));
            $this->get("/api/me/email/{$link}/{$user->id}/".$params['hash'])
                ->assertRedirect($this->redirectFor('invalid'));
            $this->get(URL::temporarySignedRoute("me.email.{$link}", now()->addHour(), $params))
                ->assertRedirect($this->redirectFor('invalid'));
        }

        $this->assertSame('old@example.com', $user->refresh()->email);
        $this->assertSame('new@example.com', $user->pending_email);
    }

    public function test_one_link_cannot_stand_in_for_another(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $this->requestChange($user, 'new@example.com');
        $cancelUrl = $this->lastMailed(EmailChangeRequestedMail::class, 'old@example.com')->cancelUrl;
        $hash = basename(parse_url($cancelUrl, PHP_URL_PATH));

        $this->get(URL::temporarySignedRoute('me.email.confirm', now()->addHour(), ['id' => $user->id, 'hash' => $hash]))
            ->assertRedirect($this->redirectFor('invalid'));

        $this->assertSame('old@example.com', $user->refresh()->email);
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

    public function test_resend_mails_the_link_the_current_stage_waits_for(): void
    {
        $user = $this->socialOnlyUser();
        $this->as($user)->putJson('/api/me/email', ['email' => 'new@example.com'])->assertOk();

        $this->as($user)->postJson('/api/me/email/resend')->assertOk()->assertJsonPath('pending_email_awaiting_current', true);

        Mail::assertSent(AuthorizeEmailChangeMail::class, fn (AuthorizeEmailChangeMail $mail) => $mail->hasTo('old@example.com'));
        Mail::assertSent(AuthorizeEmailChangeMail::class, 2);
        Mail::assertNotSent(ConfirmEmailChangeMail::class);

        $this->get($this->lastMailed(AuthorizeEmailChangeMail::class, 'old@example.com')->url)
            ->assertRedirect($this->redirectFor('check-new-inbox'));
        $this->as($user)->postJson('/api/me/email/resend')->assertOk();

        Mail::assertSent(ConfirmEmailChangeMail::class, 2);
        Mail::assertSent(AuthorizeEmailChangeMail::class, 2);
        $this->get($this->lastMailed(ConfirmEmailChangeMail::class, 'new@example.com')->url)
            ->assertRedirect($this->redirectFor('success'));
    }

    public function test_email_change_requests_are_rate_limited_per_account(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $attempt) {
            $this->as($user)->putJson('/api/me/email', ['email' => "new{$attempt}@example.com", 'current_password' => 'password'])->assertOk();
        }

        $this->as($user)->putJson('/api/me/email', ['email' => 'new4@example.com', 'current_password' => 'password'])->assertTooManyRequests();
    }

    public function test_the_email_change_endpoints_need_a_signed_in_verified_user(): void
    {
        $this->putJson('/api/me/email', ['email' => 'new@example.com'])->assertUnauthorized();
        $this->postJson('/api/me/email/resend')->assertUnauthorized();
        $this->deleteJson('/api/me/email')->assertUnauthorized();

        $unverified = User::factory()->unverified()->create();
        $this->as($unverified)->putJson('/api/me/email', ['email' => 'new@example.com', 'current_password' => 'password'])->assertForbidden();
        $this->as($unverified)->postJson('/api/me/email/resend')->assertForbidden();
        $this->as($unverified)->deleteJson('/api/me/email')->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_the_emails_render_their_links_and_the_masked_address(): void
    {
        $confirm = new ConfirmEmailChangeMail('Jane', 'http://api.test/confirm-link', 60);
        $confirm->assertSeeInHtml('http://api.test/confirm-link', false);
        $confirm->assertSeeInText('expires in 60 minutes');

        $requested = new EmailChangeRequestedMail('Jane', 'n***@example.com', 'http://api.test/cancel-link');
        $requested->assertSeeInHtml('n***@example.com');
        $requested->assertSeeInHtml('http://api.test/cancel-link', false);
        $requested->assertSeeInText('http://api.test/cancel-link');
        $requested->assertSeeInText(self::FRONT.'/edit-profile');
        $requested->assertSeeInText('voids any pending email change');
        $requested->assertDontSeeInHtml('expires in');

        $authorize = new AuthorizeEmailChangeMail('Jane', 'n***@example.com', 'http://api.test/authorize-link', 'http://api.test/cancel-link', 60);
        $authorize->assertSeeInHtml('n***@example.com');
        $authorize->assertSeeInHtml('http://api.test/authorize-link', false);
        $authorize->assertSeeInHtml('http://api.test/cancel-link', false);
        $authorize->assertSeeInText('http://api.test/authorize-link');
        $authorize->assertSeeInText('http://api.test/cancel-link');
        $authorize->assertSeeInText('expires in 60 minutes');

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
