<?php

namespace Tests\Feature\Auth;

use App\Auth\Mail\ResetPasswordMail;
use App\Auth\Mail\VerifyEmailMail;
use App\Models\PasswordReset;
use App\Models\RefreshToken;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AccountFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('s', 40), 'services.frontend_url' => 'http://front.test']);
    }

    private function resetUrlParts(ResetPasswordMail $mail): array
    {
        parse_str(parse_url($mail->url, PHP_URL_QUERY), $q);

        return $q;
    }

    public function test_registration_sends_verification_email(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Jane', 'email' => 'jane@example.com',
            'password' => 'secret1234', 'password_confirmation' => 'secret1234',
        ])->assertCreated();

        Mail::assertSent(VerifyEmailMail::class, fn ($m) => $m->hasTo('jane@example.com'));
    }

    public function test_signed_link_verifies_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('auth.verify-email', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect('http://front.test/verify-email?status=success');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unsigned_expired_or_wrong_hash_links_do_not_verify(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get("/api/auth/verify-email/{$user->id}/".sha1($user->email))->assertForbidden();

        $expired = URL::temporarySignedRoute('auth.verify-email', now()->subMinute(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->get($expired)->assertForbidden();

        $wrong = URL::temporarySignedRoute('auth.verify-email', now()->addMinutes(5), [
            'id' => $user->id, 'hash' => sha1('other@example.com'),
        ]);
        $this->get($wrong)->assertRedirect('http://front.test/verify-email?status=invalid');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_verification_is_uniform_and_only_mails_unverified_users(): void
    {
        Mail::fake();
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create();

        foreach ([$unverified->email, $verified->email, 'ghost@example.com'] as $email) {
            $this->postJson('/api/auth/resend-verification', ['email' => $email])
                ->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'If the account exists'));
        }

        Mail::assertSent(VerifyEmailMail::class, 1);
    }

    public function test_resend_verification_is_throttled(): void
    {
        Mail::fake();
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/resend-verification', ['email' => 'a@example.com'])->assertOk();
        }
        $this->postJson('/api/auth/resend-verification', ['email' => 'a@example.com'])->assertStatus(429);
    }

    public function test_forgot_password_response_is_identical_for_unknown_email(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $known = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'ghost@example.com']);

        $known->assertOk();
        $this->assertSame($known->getContent(), $unknown->getContent());
        Mail::assertSent(ResetPasswordMail::class, 1);
        $this->assertDatabaseCount('password_resets', 1);
    }

    public function test_reset_password_flow_hashes_token_is_single_use_and_revokes_refresh_tokens(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        RefreshToken::create([
            'user_id' => $user->id, 'token_hash' => 'x', 'family_id' => 'f',
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        $plain = null;
        Mail::assertSent(ResetPasswordMail::class, function ($mail) use (&$plain) {
            $plain = $this->resetUrlParts($mail)['token'];

            return true;
        });
        $this->assertNotSame($plain, PasswordReset::first()->token);

        $payload = ['email' => $user->email, 'token' => $plain, 'password' => 'newsecret99', 'password_confirmation' => 'newsecret99'];
        $this->postJson('/api/auth/reset-password', $payload)->assertOk();

        $this->assertTrue(Hash::check('newsecret99', $user->fresh()->password));
        $this->assertDatabaseCount('password_resets', 0);
        $this->assertNotNull(RefreshToken::where('user_id', $user->id)->first()->revoked_at);

        $this->postJson('/api/auth/reset-password', $payload)->assertStatus(422);
    }

    public function test_reset_password_rejects_bad_and_expired_tokens(): void
    {
        $user = User::factory()->create();
        $record = new PasswordReset(['email' => $user->email, 'token' => Hash::make('goodtoken')]);
        $record->created_at = now()->subMinutes(61);
        $record->save();

        $base = ['email' => $user->email, 'password' => 'newsecret99', 'password_confirmation' => 'newsecret99'];

        $this->postJson('/api/auth/reset-password', $base + ['token' => 'goodtoken'])->assertStatus(422);

        $record->created_at = now();
        $record->save();
        $this->postJson('/api/auth/reset-password', $base + ['token' => 'badtoken'])->assertStatus(422);
        $this->assertFalse(Hash::check('newsecret99', $user->fresh()->password));
    }

    private function mockSocialite(string $provider, SocialiteUser $socialUser): void
    {
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    private function socialUser(string $id, ?string $email, array $raw = []): SocialiteUser
    {
        $u = new SocialiteUser;
        $u->map(['id' => $id, 'name' => 'Soc User', 'nickname' => 'soc', 'email' => $email, 'avatar' => null]);
        $u->token = 'provider-access-token';
        $u->user = $raw;

        return $u;
    }

    private function hitSocialCallback(string $provider)
    {
        return $this->withUnencryptedCookie('oauth_state', 'abc')->get("/api/auth/social/{$provider}/callback?state=abc");
    }

    public function test_social_callback_creates_verified_user_and_issues_cookies(): void
    {
        $this->mockSocialite('github', $this->socialUser('gh1', 'new@example.com'));

        $response = $this->hitSocialCallback('github');

        $response->assertRedirect('http://front.test/auth/callback?status=success');
        $this->assertNotNull($response->getCookie('access_token', false));
        $this->assertNotNull($response->getCookie('refresh_token', false));

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $account = SocialAccount::firstOrFail();
        $this->assertSame('provider-access-token', $account->access_token);
        $this->assertNotSame('provider-access-token', $account->getRawOriginal('access_token'));
    }

    public function test_social_callback_links_existing_user_by_verified_email_and_verifies_them(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'me@example.com']);
        $this->mockSocialite('google', $this->socialUser('g1', 'me@example.com', ['email_verified' => true]));

        $this->hitSocialCallback('google')->assertRedirect('http://front.test/auth/callback?status=success');

        $this->assertSame(1, User::count());
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame($user->id, SocialAccount::first()->user_id);
    }

    public function test_social_callback_does_not_verify_with_unverified_provider_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'me@example.com']);
        $this->mockSocialite('google', $this->socialUser('g1', 'me@example.com', ['email_verified' => false]));

        $this->hitSocialCallback('google')
            ->assertRedirect('http://front.test/auth/callback?status=error&reason=email_unverified');

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_social_login_takes_over_unverified_local_account_by_resetting_its_password(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'me@example.com', 'password' => 'attacker-password']);
        $oldHash = $user->password;
        $this->mockSocialite('google', $this->socialUser('g1', 'me@example.com', ['email_verified' => true]));

        $this->hitSocialCallback('google')->assertRedirect('http://front.test/auth/callback?status=success');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame($oldHash, $user->password);
        $this->postJson('/api/auth/login', ['email' => 'me@example.com', 'password' => 'attacker-password'])
            ->assertUnauthorized();
    }

    public function test_social_callback_reuses_linked_account(): void
    {
        $user = User::factory()->create();
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'github', 'provider_id' => 'gh9', 'access_token' => 'old']);
        $this->mockSocialite('github', $this->socialUser('gh9', 'different@example.com'));

        $this->hitSocialCallback('github')->assertRedirect('http://front.test/auth/callback?status=success');

        $this->assertSame(1, User::count());
        $this->assertSame('provider-access-token', SocialAccount::first()->access_token);
    }

    public function test_social_callback_rejects_bad_state_and_unknown_provider(): void
    {
        $this->withUnencryptedCookie('oauth_state', 'abc')
            ->get('/api/auth/social/github/callback?state=zzz')
            ->assertRedirect('http://front.test/auth/callback?status=error&reason=invalid_state');

        $this->get('/api/auth/social/slack/callback')->assertNotFound();
        $this->get('/api/auth/social/slack')->assertNotFound();
    }

    public function test_social_redirect_sets_state_cookie(): void
    {
        // The redirect refuses to run without real-looking credentials, so the test can't depend on .env.
        config(['services.google.client_id' => 'test-client-id', 'services.google.client_secret' => 'test-client-secret']);
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('with')->with(Mockery::on(fn ($a) => isset($a['state'])))->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn(redirect('https://accounts.example/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get('/api/auth/social/google');

        $response->assertRedirect('https://accounts.example/auth');
        $this->assertNotNull($response->getCookie('oauth_state', false));
    }

    public function test_social_redirect_is_unavailable_without_real_credentials(): void
    {
        config(['services.github.client_id' => 'CHANGE_ME', 'services.github.client_secret' => 'test-client-secret']);

        $this->getJson('/api/auth/social/github')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Github sign-in is not configured. Add valid OAuth client credentials to the backend environment.');
    }

    public function test_discord_redirect_and_callback_sign_the_user_in(): void
    {
        config(['services.discord' => [
            'client_id' => 'discord-id',
            'client_secret' => 'discord-secret',
            'redirect' => 'http://api.test/api/auth/social/discord/callback',
        ]]);
        Http::fake([
            'discord.com/api/oauth2/token' => Http::response(['access_token' => 'discord-token']),
            'discord.com/api/users/@me' => Http::response([
                'id' => '42', 'username' => 'ada', 'global_name' => 'Ada', 'email' => 'ada@example.com', 'verified' => true, 'avatar' => 'abc',
            ]),
        ]);

        $redirect = $this->get('/api/auth/social/discord');
        $redirect->assertRedirectContains('https://discord.com/oauth2/authorize?client_id=discord-id');
        $state = $redirect->getCookie('oauth_state', false)->getValue();

        $this->withUnencryptedCookie('oauth_state', $state)
            ->get("/api/auth/social/discord/callback?state={$state}&code=the-code")
            ->assertRedirect('http://front.test/auth/callback?status=success');

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com', 'avatar' => 'https://cdn.discordapp.com/avatars/42/abc.png']);
        $this->assertDatabaseHas('social_accounts', ['provider' => 'discord', 'provider_id' => '42']);
    }

    public function test_discord_callback_without_a_code_or_user_redirects_with_a_provider_error(): void
    {
        config(['services.discord' => ['client_id' => 'discord-id', 'client_secret' => 'discord-secret', 'redirect' => 'http://api.test/cb']]);
        Http::fake([
            'discord.com/api/oauth2/token' => Http::response(['access_token' => 'discord-token']),
            'discord.com/api/users/@me' => Http::response(['username' => 'no-id']),
        ]);
        $failure = 'http://front.test/auth/callback?status=error&reason=provider_error';

        $this->withUnencryptedCookie('oauth_state', 'abc')
            ->get('/api/auth/social/discord/callback?state=abc')
            ->assertRedirect($failure);
        $this->withUnencryptedCookie('oauth_state', 'abc')
            ->get('/api/auth/social/discord/callback?state=abc&code=the-code')
            ->assertRedirect($failure);
    }

    public function test_registered_event_is_listened_to(): void
    {
        Mail::fake();
        $this->withoutDefer();
        event(new Registered(User::factory()->unverified()->create()));
        Mail::assertSent(VerifyEmailMail::class);
    }
}
