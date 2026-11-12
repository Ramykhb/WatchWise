<?php

namespace Tests\Feature\Auth;

use App\Auth\Factories\TokenFactory;
use App\Auth\Mail\VerifyEmailMail;
use App\Models\User;
use App\Support\Exceptions\MailNotDelivered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Sleep;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class BrevoVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.frontend_url' => 'http://front.test',
            'mail.default' => 'brevo',
            'mail.mailers.brevo.key' => 'test-brevo-key',
            'mail.from.address' => 'sender@example.com',
            'mail.from.name' => 'WatchWise',
        ]);
        Http::preventStrayRequests();
    }

    public function test_signup_sends_a_real_mailable_through_brevo_and_the_link_unlocks_the_account(): void
    {
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-message'], 201)]);
        Event::fake([Verified::class]);

        $signup = $this->postJson('/api/auth/register', [
            'name' => 'Jane', 'email' => 'jane@example.com',
            'password' => 'secret1234', 'password_confirmation' => 'secret1234',
        ])->assertCreated()->assertJsonPath('user.email_verified_at', null);

        $url = null;
        Http::assertSent(function ($request) use (&$url): bool {
            preg_match('~https?://[^\\s"<>]+/api/auth/verify-email/[^\\s"<>]+~', html_entity_decode($request['htmlContent']), $matches);
            $url = $matches[0] ?? null;

            return $request->hasHeader('api-key', 'test-brevo-key')
                && $request['sender'] === ['email' => 'sender@example.com', 'name' => 'WatchWise']
                && $request['to'][0]['email'] === 'jane@example.com'
                && $request['subject'] === 'Verify your WatchWise email'
                && str_contains($request['textContent'], '60 minutes')
                && $url !== null;
        });
        Http::assertSentCount(1);
        $jwt = $signup->getCookie('access_token', false)->getValue();
        $this->withToken($jwt)->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.email_verified_at', null);
        $this->getJson('/api/home')->assertForbidden()->assertJsonPath('code', 'email_unverified');

        $this->postJson($url)->assertOk()->assertExactJson(['outcome' => 'success']);
        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        Event::assertDispatched(Verified::class, fn ($event) => $event->user->id === $user->id);
        // The existing access token works immediately: verification is read from the DB.
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.email_verified_at', $user->email_verified_at->toJSON());
        $this->getJson('/api/home')->assertOk();

        $timestamp = $user->email_verified_at->toJSON();
        $this->travel(1)->minutes();
        $this->postJson($url)->assertOk()->assertExactJson(['outcome' => 'already-verified']);
        $this->assertSame($timestamp, $user->fresh()->email_verified_at->toJSON());
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_tampering_with_expiry_or_account_does_not_verify_either_account(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('auth.verify-email', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $tampered = preg_replace('/expires=\d+/', 'expires=1', $url);
        $this->postJson($tampered)->assertForbidden()->assertExactJson(['outcome' => 'invalid']);
        $this->postJson(str_replace('/'.$user->id.'/', '/'.$other->id.'/', $url))->assertForbidden()->assertExactJson(['outcome' => 'invalid']);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_resending_after_expiry_provides_a_working_link(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('auth.verify-email', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect('http://front.test/account-link/result?kind=verify-email&outcome=expired');
        $this->postJson($url)->assertStatus(410)->assertExactJson(['outcome' => 'expired']);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->postJson('/api/auth/resend-verification', ['email' => $user->email])->assertOk();
        $freshUrl = null;
        Mail::assertSent(VerifyEmailMail::class, function ($mail) use (&$freshUrl): bool {
            $freshUrl = $mail->url;

            return true;
        });
        $this->postJson($freshUrl)->assertOk()->assertExactJson(['outcome' => 'success']);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_links_for_changed_email_and_deleted_accounts_are_invalid(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('auth.verify-email', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $user->update(['email' => 'changed@example.com']);
        $this->postJson($url)->assertOk()->assertExactJson(['outcome' => 'invalid']);
        $this->assertNull($user->fresh()->email_verified_at);
        $user->delete();
        $this->postJson($url)->assertOk()->assertExactJson(['outcome' => 'invalid']);
    }

    public function test_brevo_failure_does_not_lose_the_registered_account(): void
    {
        Sleep::fake();
        Exceptions::fake();
        Http::fakeSequence('api.brevo.com/v3/smtp/email')
            ->push(['message' => 'Invalid sender'], 400)
            ->push(['message' => 'Invalid sender'], 400)
            ->push(['messageId' => 'retry-message'], 201);
        $this->postJson('/api/auth/register', [
            'name' => 'Jane', 'email' => 'jane@example.com',
            'password' => 'secret1234', 'password_confirmation' => 'secret1234',
        ])->assertCreated()->assertJsonPath('user.email_verified_at', null);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'email_verified_at' => null]);
        // Tried twice after the response (1 s apart), then reported once without the address or link.
        Http::assertSentCount(2);
        Sleep::assertSequence([Sleep::for(1)->second()]);
        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(fn (MailNotDelivered $e): bool => $e->getMessage() === 'VerifyEmailMail was not delivered after 2 attempt(s): TransportException.');
        $this->postJson('/api/auth/resend-verification', ['email' => 'jane@example.com'])->assertOk();
        Http::assertSentCount(3);
        Exceptions::assertReportedCount(1);
    }

    public function test_missing_brevo_key_fails_without_a_network_request(): void
    {
        config(['mail.mailers.brevo.key' => '']);
        Http::fake();
        $user = User::factory()->unverified()->create();
        try {
            Mail::to($user->email)->send(new VerifyEmailMail($user, 'http://example.test/verify'));
            $this->fail('Missing credentials must not silently discard mail.');
        } catch (TransportException $exception) {
            $this->assertStringContainsString('BREVO_API_KEY', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_resend_rejects_invalid_email_and_unverified_sessions_can_log_out(): void
    {
        $this->postJson('/api/auth/resend-verification', ['email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $user = User::factory()->unverified()->create();
        [$jwt] = app(TokenFactory::class)->issueAccessToken($user);
        $this->withToken($jwt)->postJson('/api/auth/logout')->assertOk();
    }
}
