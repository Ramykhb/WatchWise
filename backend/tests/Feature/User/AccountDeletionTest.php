<?php

namespace Tests\Feature\User;

use App\Models\RefreshToken;
use App\Models\User;
use App\User\InterfaceRepository\AccountDeletionRepositoryInterface;
use App\User\Mail\AccountDeletedMail;
use App\User\Mail\ConfirmAccountDeletionMail;
use App\User\Repository\AccountDeletionRepository;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Social\SocialTestCase;

class AccountDeletionTest extends SocialTestCase
{
    private const FRONT = 'http://front.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.frontend_url' => self::FRONT]);
        Mail::fake();
        Storage::fake('public');
    }

    /** An account created by social sign-in that never set a password. */
    private function socialOnlyUser(): User
    {
        return User::factory()->withoutPassword()->create(['email' => 'me@example.com']);
    }

    /** Gives the account an avatar file stored by this app and returns its path on the public disk. */
    private function withStoredAvatar(User $user): string
    {
        Storage::disk('public')->put('avatars/me.png', 'image');
        $user->forceFill(['avatar' => 'avatars/me.png'])->save();

        return 'avatars/me.png';
    }

    /** Asks to delete an account whose password proves nothing and returns the mail with its links. */
    private function requestDeletion(User $user): ConfirmAccountDeletionMail
    {
        $this->as($user)->deleteJson('/api/me')->assertAccepted();

        $mail = Mail::sent(ConfirmAccountDeletionMail::class, fn ($mail) => $mail->hasTo($user->email))->last();
        $this->assertNotNull($mail, 'No deletion link was mailed.');

        return $mail;
    }

    private function redirectFor(string $status): string
    {
        return self::FRONT.($status === 'deleted' ? '/signin' : '/settings').'?account_deletion='.$status;
    }

    public function test_an_account_with_a_password_is_deleted_with_it(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        $avatar = $this->withStoredAvatar($user);

        $this->as($user)->deleteJson('/api/me')
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Enter your password to delete your account.');
        $this->as($user)->deleteJson('/api/me', ['password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'That password is incorrect.');
        $this->assertModelExists($user);

        $this->as($user)->deleteJson('/api/me', ['password' => 'password'])
            ->assertOk()
            ->assertExactJson(['message' => 'Account deleted.']);

        $this->assertModelMissing($user);
        Storage::disk('public')->assertMissing($avatar);
        Mail::assertNotSent(ConfirmAccountDeletionMail::class);
        Mail::assertSent(AccountDeletedMail::class, fn (AccountDeletedMail $mail) => $mail->hasTo('me@example.com'));
    }

    public function test_an_account_without_a_password_is_mailed_a_link_and_nothing_is_deleted(): void
    {
        $user = $this->socialOnlyUser();
        $this->as($user)->getJson('/api/me')->assertJsonPath('deletion_by_email', true)->assertJsonPath('deletion_pending', false);

        $this->as($user)->deleteJson('/api/me')
            ->assertAccepted()
            ->assertExactJson(['message' => 'We sent a link to me@example.com. Your account is deleted once you open it.', 'deletion_pending' => true]);

        $this->assertModelExists($user);
        Mail::assertSent(ConfirmAccountDeletionMail::class, 1);
        Mail::assertSent(ConfirmAccountDeletionMail::class, fn (ConfirmAccountDeletionMail $mail) => $mail->hasTo('me@example.com'));
        Mail::assertNotSent(AccountDeletedMail::class);
        $this->as($user)->getJson('/api/me')->assertJsonPath('deletion_pending', true);
    }

    public function test_the_link_deletes_the_account_and_its_avatar(): void
    {
        $user = $this->socialOnlyUser();
        RefreshToken::factory()->for($user)->create();
        $avatar = $this->withStoredAvatar($user);
        $mail = $this->requestDeletion($user);

        $this->get($mail->url)->assertRedirect($this->redirectFor('deleted'));

        $this->assertModelMissing($user);
        $this->assertSame(0, RefreshToken::where('user_id', $user->id)->count());
        Storage::disk('public')->assertMissing($avatar);
        Mail::assertSent(AccountDeletedMail::class, fn (AccountDeletedMail $mail) => $mail->hasTo('me@example.com'));
    }

    public function test_the_link_works_once(): void
    {
        $user = $this->socialOnlyUser();
        $mail = $this->requestDeletion($user);
        $this->get($mail->url)->assertRedirect($this->redirectFor('deleted'));

        $this->get($mail->url)->assertRedirect($this->redirectFor('invalid'));
        $this->get($mail->cancelUrl)->assertRedirect($this->redirectFor('invalid'));
        Mail::assertSent(AccountDeletedMail::class, 1);
    }

    public function test_a_replaced_request_voids_its_links(): void
    {
        $user = $this->socialOnlyUser();
        $first = $this->requestDeletion($user);
        $second = $this->requestDeletion($user);

        $this->get($first->url)->assertRedirect($this->redirectFor('invalid'));
        $this->get($first->cancelUrl)->assertRedirect($this->redirectFor('invalid'));
        $this->assertModelExists($user);

        $this->get($second->url)->assertRedirect($this->redirectFor('deleted'));
        $this->assertModelMissing($user);
    }

    public function test_a_request_replaced_while_its_link_is_opened_deletes_nothing(): void
    {
        $user = $this->socialOnlyUser();
        $mail = $this->requestDeletion($user);
        // A newer request lands between the link check and the compare-and-set write, as a parallel request would.
        $this->app->bind(AccountDeletionRepositoryInterface::class, fn () => new class extends AccountDeletionRepository
        {
            public function clearDeletionRequest(User $user, ?string $nonce = null): bool
            {
                User::whereKey($user->id)->update(['deletion_nonce' => 'newer']);

                return parent::clearDeletionRequest($user, $nonce);
            }
        });

        $this->get($mail->url)->assertRedirect($this->redirectFor('invalid'));

        $this->assertModelExists($user);
        Mail::assertNotSent(AccountDeletedMail::class);
    }

    public function test_a_link_cannot_stand_in_for_another(): void
    {
        $user = $this->socialOnlyUser();
        $mail = $this->requestDeletion($user);
        $cancelHash = basename(parse_url($mail->cancelUrl, PHP_URL_PATH));

        $this->get(URL::temporarySignedRoute('me.delete.confirm', now()->addHour(), ['id' => $user->id, 'hash' => $cancelHash]))
            ->assertRedirect($this->redirectFor('invalid'));

        $this->assertModelExists($user);
    }

    public function test_expired_and_tampered_links_redirect_without_deleting(): void
    {
        $user = $this->socialOnlyUser();
        $mail = $this->requestDeletion($user);
        $hash = basename(parse_url($mail->url, PHP_URL_PATH));

        foreach (['confirm', 'cancel'] as $link) {
            $this->get(URL::temporarySignedRoute("me.delete.{$link}", now()->subMinute(), ['id' => $user->id, 'hash' => $hash]))
                ->assertRedirect($this->redirectFor('expired'));
            $this->get("/api/me/delete/{$link}/{$user->id}/{$hash}")->assertRedirect($this->redirectFor('invalid'));
            $this->get(URL::temporarySignedRoute("me.delete.{$link}", now()->addHour(), ['id' => $user->id, 'hash' => str_repeat('a', 64)]))
                ->assertRedirect($this->redirectFor('invalid'));
        }

        $this->assertModelExists($user);
        $this->as($user)->getJson('/api/me')->assertJsonPath('deletion_pending', true);
    }

    public function test_a_request_older_than_an_hour_is_no_longer_pending(): void
    {
        $user = $this->socialOnlyUser();
        $mail = $this->requestDeletion($user);

        // The signature is still valid; the request itself is too old.
        User::whereKey($user->id)->update(['deletion_requested_at' => now()->subMinutes(61)]);

        $this->as($user)->getJson('/api/me')->assertJsonPath('deletion_pending', false);
        $this->get($mail->url)->assertRedirect($this->redirectFor('invalid'));
        $this->assertModelExists($user);
    }

    public function test_setting_a_password_voids_the_link_and_the_pending_request(): void
    {
        $user = $this->socialOnlyUser();
        $mail = $this->requestDeletion($user);

        $this->as($user)->putJson('/api/me/password/set', ['password' => 'chosen12345', 'password_confirmation' => 'chosen12345'])->assertOk();

        $this->as($user)->getJson('/api/me')->assertJsonPath('deletion_pending', false);
        $this->get($mail->url)->assertRedirect($this->redirectFor('invalid'));
        $this->assertModelExists($user);
    }

    public function test_the_link_is_bound_to_the_password_hash_and_the_email(): void
    {
        $user = $this->socialOnlyUser();
        $mail = $this->requestDeletion($user);
        $nonce = $user->refresh()->deletion_nonce;

        // Written by a path that keeps the pending request, the link still stops working.
        User::whereKey($user->id)->update(['email' => 'other@example.com']);
        $this->get($mail->url)->assertRedirect($this->redirectFor('invalid'));

        User::whereKey($user->id)->update(['email' => 'me@example.com', 'password' => bcrypt('somethingelse1')]);
        $this->get($mail->url)->assertRedirect($this->redirectFor('invalid'));

        $this->assertModelExists($user);
        $this->assertSame($nonce, $user->refresh()->deletion_nonce);
    }

    public function test_a_first_password_set_in_settings_still_confirms_by_email(): void
    {
        $user = $this->socialOnlyUser();
        $this->as($user)->putJson('/api/me/password/set', ['password' => 'chosen12345', 'password_confirmation' => 'chosen12345'])->assertOk();
        $this->as($user)->getJson('/api/me')->assertJsonPath('has_password', true)->assertJsonPath('deletion_by_email', true);

        $this->as($user)->deleteJson('/api/me', ['password' => 'chosen12345'])->assertAccepted();

        $this->assertModelExists($user);
        Mail::assertSent(ConfirmAccountDeletionMail::class, fn (ConfirmAccountDeletionMail $mail) => $mail->hasTo('me@example.com'));
    }

    public function test_cancelling_from_settings_voids_the_link(): void
    {
        $user = $this->socialOnlyUser();
        $mail = $this->requestDeletion($user);

        $this->as($user)->deleteJson('/api/me/delete-request')->assertOk()->assertJsonPath('deletion_pending', false);

        $this->get($mail->url)->assertRedirect($this->redirectFor('invalid'));
        $this->assertModelExists($user);
        $this->as($user)->deleteJson('/api/me/delete-request')->assertOk();
    }

    public function test_the_cancel_link_drops_the_request(): void
    {
        $user = $this->socialOnlyUser();
        $mail = $this->requestDeletion($user);

        $this->get($mail->cancelUrl)->assertRedirect($this->redirectFor('cancelled'));

        $this->get($mail->url)->assertRedirect($this->redirectFor('invalid'));
        $this->assertModelExists($user);
        $this->as($user)->getJson('/api/me')->assertJsonPath('deletion_pending', false);
    }

    public function test_deletion_requests_are_rate_limited_per_account(): void
    {
        $user = $this->socialOnlyUser();

        foreach (range(1, 5) as $attempt) {
            $this->as($user)->deleteJson('/api/me')->assertAccepted();
        }

        $this->as($user)->deleteJson('/api/me')->assertTooManyRequests();
        $this->as(User::factory()->create())->deleteJson('/api/me', ['password' => 'wrong'])->assertUnprocessable();
    }

    public function test_the_deletion_endpoints_need_a_signed_in_verified_user(): void
    {
        $this->deleteJson('/api/me')->assertUnauthorized();
        $this->deleteJson('/api/me/delete-request')->assertUnauthorized();

        $unverified = User::factory()->unverified()->create();
        $this->as($unverified)->deleteJson('/api/me', ['password' => 'password'])->assertForbidden();
        $this->as($unverified)->deleteJson('/api/me/delete-request')->assertForbidden();

        $this->assertModelExists($unverified);
        Mail::assertNothingSent();
    }

    public function test_the_emails_render_their_links(): void
    {
        $confirm = new ConfirmAccountDeletionMail('Jane', 'http://api.test/confirm-link', 'http://api.test/cancel-link', 60);
        $confirm->assertSeeInHtml('http://api.test/confirm-link', false);
        $confirm->assertSeeInHtml('http://api.test/cancel-link', false);
        $confirm->assertSeeInText('http://api.test/confirm-link');
        $confirm->assertSeeInText('http://api.test/cancel-link');
        $confirm->assertSeeInText('expires in 60 minutes');

        (new AccountDeletedMail('Jane'))->assertSeeInText(self::FRONT.'/signup');
    }
}
