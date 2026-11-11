<?php

namespace Tests\Feature\Auth;

use App\Auth\DTOs\Requests\ResetPasswordData;
use App\Auth\Exceptions\InvalidResetToken;
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceServices\PasswordResetServiceInterface;
use App\Auth\Mail\PasswordChangedMail;
use App\Auth\Mail\ResetPasswordMail;
use App\Models\PasswordReset;
use App\Models\User;
use App\Support\Exceptions\MailNotDelivered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/** POST /api/auth/forgot-password and /api/auth/reset-password: single-use tokens, inbox proof, the notice mail. */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.frontend_url' => 'http://front.test']);
    }

    private function storeToken(string $email, string $plain): void
    {
        $record = new PasswordReset(['email' => $email, 'token' => hash('sha256', $plain)]);
        $record->created_at = now();
        $record->save();
    }

    /** @return array<string, string> */
    private function body(string $email, string $token = 'goodtoken', string $password = 'newsecret99'): array
    {
        return ['email' => $email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password];
    }

    /** Makes the default mailer the Brevo API; the test fakes its answers. */
    private function brevoMailer(): void
    {
        config([
            'mail.default' => 'brevo',
            'mail.mailers.brevo.key' => 'test-brevo-key',
            'mail.from.address' => 'sender@example.com',
            'mail.from.name' => 'WatchWise',
        ]);
        Http::preventStrayRequests();
    }

    /** Brevo answers every attempt with a 503. */
    private function failingMailTransport(): void
    {
        $this->brevoMailer();
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response(['message' => 'down'], 503)]);
    }

    public function test_the_stored_token_is_the_sha256_of_the_mailed_one(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail): bool {
            parse_str((string) parse_url($mail->url, PHP_URL_QUERY), $query);

            return PasswordReset::sole()->token === hash('sha256', $query['token']);
        });
    }

    public function test_a_reset_verifies_the_address_and_notifies_the_account(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $this->storeToken($user->email, 'goodtoken');
        [$jwt] = app(TokenFactory::class)->issueAccessToken($user);
        $this->withToken($jwt)->getJson('/api/home')->assertForbidden()->assertJsonPath('code', 'email_unverified');

        $this->postJson('/api/auth/reset-password', $this->body($user->email))->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
        Mail::assertSent(PasswordChangedMail::class, fn (PasswordChangedMail $mail): bool => $mail->hasTo($user->email));
        // The reset signed every device out; signing in again lands past the verify wall.
        $login = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'newsecret99'])->assertOk();
        $this->withToken($login->getCookie('access_token', false)->getValue())->getJson('/api/home')->assertOk();
    }

    public function test_a_reset_keeps_the_verification_time_of_a_verified_address(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email_verified_at' => now()->subYear()]);
        $verifiedAt = $user->email_verified_at;
        $this->storeToken($user->email, 'goodtoken');

        $this->postJson('/api/auth/reset-password', $this->body($user->email))->assertOk();

        $this->assertTrue($verifiedAt->equalTo($user->fresh()->email_verified_at));
    }

    public function test_a_token_used_twice_works_only_once(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $this->storeToken($user->email, 'goodtoken');
        $service = app(PasswordResetServiceInterface::class);

        // Two requests with the same token: the first consumes the row inside its transaction, the second finds none.
        $service->reset(new ResetPasswordData($user->email, 'goodtoken', 'first12345'));
        try {
            $service->reset(new ResetPasswordData($user->email, 'goodtoken', 'second1234'));
            $this->fail('A used token must be refused.');
        } catch (InvalidResetToken) {
            $this->assertTrue(Hash::check('first12345', $user->fresh()->password));
        }

        $this->assertDatabaseCount('password_resets', 0);
        app(DeferredCallbackCollection::class)->invoke();
        Mail::assertSent(PasswordChangedMail::class, 1);
    }

    public function test_a_wrong_token_changes_nothing_and_keeps_the_valid_one(): void
    {
        $user = User::factory()->create();
        $this->storeToken($user->email, 'goodtoken');

        $this->postJson('/api/auth/reset-password', $this->body($user->email, 'badtoken'))->assertStatus(422);

        $this->assertDatabaseCount('password_resets', 1);
        $this->postJson('/api/auth/reset-password', $this->body($user->email))->assertOk();
    }

    public function test_an_unknown_address_gets_the_same_answer_as_a_wrong_token(): void
    {
        $user = User::factory()->create();
        $this->storeToken($user->email, 'goodtoken');

        $wrong = $this->postJson('/api/auth/reset-password', $this->body($user->email, 'badtoken'))->assertStatus(422);
        $unknown = $this->postJson('/api/auth/reset-password', $this->body('ghost@example.com'))->assertStatus(422);

        $this->assertSame($wrong->json(), $unknown->json());
    }

    public function test_a_failing_reset_mail_is_retried_then_reported_without_the_address_or_link(): void
    {
        Sleep::fake();
        Exceptions::fake();
        $this->failingMailTransport();
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'owner@example.com'])->assertOk();

        Http::assertSentCount(2);
        Sleep::assertSequence([Sleep::for(1)->second()]);
        // The token is stored once, not once per attempt.
        $this->assertDatabaseCount('password_resets', 1);
        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(function (MailNotDelivered $e) use ($user): bool {
            return $e->getMessage() === 'ResetPasswordMail was not delivered after 2 attempt(s): TransportException.'
                && ! str_contains($e->getMessage(), $user->email)
                && $e->getPrevious() === null;
        });
    }

    public function test_a_failing_notice_does_not_undo_the_reset(): void
    {
        Sleep::fake();
        Exceptions::fake();
        $this->failingMailTransport();
        $user = User::factory()->create();
        $this->storeToken($user->email, 'goodtoken');

        $this->postJson('/api/auth/reset-password', $this->body($user->email))->assertOk();

        $this->assertTrue(Hash::check('newsecret99', $user->fresh()->password));
        Http::assertSentCount(2);
        Exceptions::assertReported(fn (MailNotDelivered $e): bool => $e->getMessage() === 'PasswordChangedMail was not delivered after 2 attempt(s): TransportException.');
    }

    public function test_a_mail_that_goes_through_on_a_retry_is_not_reported(): void
    {
        Sleep::fake();
        Exceptions::fake();
        $this->brevoMailer();
        Http::fakeSequence('api.brevo.com/v3/smtp/email')
            ->push(['message' => 'down'], 503)
            ->push(['messageId' => 'ok'], 201);
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        Http::assertSentCount(2);
        Exceptions::assertNothingReported();
    }
}
