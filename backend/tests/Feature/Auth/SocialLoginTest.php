<?php

namespace Tests\Feature\Auth;

use App\Auth\Exceptions\SocialAccountNotSaved;
use App\Auth\Exceptions\SocialLoginCancelled;
use App\Auth\Exceptions\SocialProviderFailed;
use App\Auth\InterfaceRepository\SocialAccountRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\Repository\SocialAccountRepository;
use App\Auth\Repository\UserRepository;
use App\Models\RefreshToken;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/** The social sign-in callback's failure paths, its state cookie, its throttling and concurrent first sign-ins. */
class SocialLoginTest extends TestCase
{
    use RefreshDatabase;
    use SignsInWithProviders;

    private const SUCCESS = 'http://front.test/auth/callback?status=success';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.frontend_url' => 'http://front.test']);
        $this->configureSocialProviders();
    }

    /** A failure redirects with `$reason`, signs nobody in and clears the state cookie so it cannot be replayed. */
    private function assertFailure(TestResponse $response, string $reason): void
    {
        $response->assertRedirect("http://front.test/auth/callback?status=error&reason={$reason}");
        $this->assertTrue($response->getCookie('oauth_state', false)->isCleared());
        $this->assertNull($response->getCookie('access_token', false));
        $this->assertNull($response->getCookie('refresh_token', false));
    }

    /** @param  array<string, mixed>  $profile  Discord's /users/@me answer */
    private function fakeDiscord(array $profile): void
    {
        Http::fake([
            'discord.com/api/oauth2/token' => Http::response(['access_token' => 'discord-token']),
            'discord.com/api/users/@me' => Http::response($profile),
        ]);
    }

    public function test_a_linked_user_signs_in_even_when_the_provider_no_longer_returns_an_email(): void
    {
        $user = User::factory()->create();
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'github', 'provider_id' => 'gh1']);
        $this->mockSocialite('github', $this->socialiteUser('gh1', null));

        $this->hitSocialCallback('github')->assertRedirect(self::SUCCESS);

        $this->assertSame(1, RefreshToken::where('user_id', $user->id)->count());
    }

    public function test_github_without_an_email_is_asked_for_one(): void
    {
        $this->mockSocialite('github', $this->socialiteUser('gh1', null));

        $this->assertFailure($this->hitSocialCallback('github'), 'email_required');

        $this->assertSame(0, User::count());
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_discord_without_an_email_is_asked_for_one(): void
    {
        $this->fakeDiscord(['id' => '42', 'username' => 'ada', 'verified' => false]);

        $this->assertFailure($this->hitSocialCallback('discord', '&code=the-code'), 'email_required');

        $this->assertSame(0, User::count());
    }

    public function test_discord_with_an_unverified_email_creates_and_links_nothing(): void
    {
        $owner = User::factory()->unverified()->create(['email' => 'ada@example.com', 'password' => 'owner-pass1']);
        $this->fakeDiscord(['id' => '42', 'username' => 'ada', 'email' => 'ada@example.com', 'verified' => false]);

        $this->assertFailure($this->hitSocialCallback('discord', '&code=the-code'), 'email_unverified');

        $this->assertSame(1, User::count());
        $this->assertSame(0, SocialAccount::count());
        $this->assertNull($owner->fresh()->email_verified_at);
        $this->assertNotNull($owner->fresh()->password);
    }

    public function test_a_disabled_linked_account_is_refused_without_any_change(): void
    {
        $placeholder = 'https://lh3.googleusercontent.com/a/letter=s96-c';
        $user = User::factory()->create(['is_active' => false, 'avatar' => $placeholder]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g1']);
        Http::fake(['people.googleapis.com/*' => Http::response(['photos' => [['metadata' => ['primary' => true], 'default' => true]]])]);
        $this->mockSocialite('google', $this->socialiteUser('g1', $user->email, ['email_verified' => true], $placeholder));
        $before = $user->fresh()->getAttributes();

        $this->assertFailure($this->hitSocialCallback('google'), 'account_disabled');

        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertSame(0, RefreshToken::count());
    }

    public function test_a_disabled_account_matched_by_email_is_refused_without_any_change(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'me@example.com', 'password' => 'owner-pass1', 'is_active' => false]);
        RefreshToken::create(['user_id' => $user->id, 'token_hash' => 'existing-session', 'family_id' => 'f', 'expires_at' => now()->addDay()]);
        $this->mockSocialite('google', $this->socialiteUser('g1', 'me@example.com', ['email_verified' => true]));
        $before = $user->fresh()->getAttributes();

        $this->assertFailure($this->hitSocialCallback('google'), 'account_disabled');

        // Not verified, password and sessions kept, session version unchanged, nothing linked.
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertNull(RefreshToken::where('token_hash', 'existing-session')->value('revoked_at'));
        $this->assertSame(1, RefreshToken::count());
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_taking_over_an_unverified_account_drops_the_email_change_its_registrant_started(): void
    {
        // Seeded directly: through the API an unverified account cannot start an email change (verified.email).
        $user = User::factory()->unverified()->create([
            'email' => 'victim@example.com',
            'password' => 'squat12345',
            'pending_email' => 'attacker@example.net',
            'pending_email_nonce' => 'attacker-nonce',
            'pending_email_authorized_at' => now(),
            'pending_email_link_sent_at' => now(),
        ]);
        $this->mockSocialite('google', $this->socialiteUser('g1', 'victim@example.com', ['email_verified' => true]));

        $callback = $this->hitSocialCallback('google')->assertRedirect(self::SUCCESS);

        $user->refresh();
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_nonce);
        $this->assertNull($user->pending_email_authorized_at);
        $this->assertNull($user->pending_email_link_sent_at);
        $this->withHeader('Authorization', 'Bearer '.$callback->getCookie('access_token', false)->getValue())
            ->postJson('/api/me/email/resend')
            ->assertUnprocessable();
    }

    public function test_a_failed_write_rolls_back_the_whole_takeover(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'me@example.com', 'password' => 'owner-pass1', 'pending_email' => 'next@example.com', 'pending_email_nonce' => 'n']);
        RefreshToken::create(['user_id' => $user->id, 'token_hash' => 'existing-session', 'family_id' => 'f', 'expires_at' => now()->addDay()]);
        $this->app->instance(SocialAccountRepositoryInterface::class, new class extends SocialAccountRepository
        {
            public function create(array $attributes): SocialAccount
            {
                throw new RuntimeException('Simulated failure after the account was taken over.');
            }
        });
        $this->mockSocialite('google', $this->socialiteUser('g1', 'me@example.com', ['email_verified' => true]));
        $before = $user->fresh()->getAttributes();

        $this->assertFailure($this->hitSocialCallback('google'), 'provider_error');

        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertNull(RefreshToken::where('token_hash', 'existing-session')->value('revoked_at'));
    }

    public function test_a_concurrent_first_sign_in_that_loses_the_social_account_race_signs_in_to_the_winner(): void
    {
        // The winning callback committed the user and the link after this one had already looked them up.
        $winner = User::factory()->create(['email' => 'new@example.com']);
        SocialAccount::create(['user_id' => $winner->id, 'provider' => 'github', 'provider_id' => 'gh1']);
        $this->missLookups(socialAccount: 1, userByEmail: 0);
        $this->mockSocialite('github', $this->socialiteUser('gh1', 'new@example.com'));

        $this->hitSocialCallback('github')->assertRedirect(self::SUCCESS);

        $this->assertSame(1, User::count());
        $this->assertSame(1, SocialAccount::count());
        $this->assertSame(1, RefreshToken::where('user_id', $winner->id)->count());
    }

    public function test_a_concurrent_first_sign_up_that_loses_the_email_race_twice_still_signs_in_to_the_winner(): void
    {
        $winner = User::factory()->create(['email' => 'new@example.com']);
        SocialAccount::create(['user_id' => $winner->id, 'provider' => 'github', 'provider_id' => 'gh1']);
        $this->missLookups(socialAccount: 2, userByEmail: 2);
        $this->mockSocialite('github', $this->socialiteUser('gh1', 'new@example.com'));

        $this->hitSocialCallback('github')->assertRedirect(self::SUCCESS);

        $this->assertSame(1, User::count());
        $this->assertSame(1, RefreshToken::where('user_id', $winner->id)->count());
    }

    public function test_a_third_collision_is_reported_without_sql_or_personal_data(): void
    {
        Exceptions::fake();
        $winner = User::factory()->create(['email' => 'new@example.com']);
        SocialAccount::create(['user_id' => $winner->id, 'provider' => 'github', 'provider_id' => 'gh1']);
        $this->missLookups(socialAccount: 3, userByEmail: 3);
        $this->mockSocialite('github', $this->socialiteUser('gh1', 'new@example.com'));

        $this->assertFailure($this->hitSocialCallback('github'), 'provider_error');

        Exceptions::assertReported(fn (SocialAccountNotSaved $e): bool => $e->getPrevious() === null
            && ! str_contains($e->getMessage(), 'new@example.com')
            && ! str_contains(strtolower($e->getMessage()), 'insert'));
        $this->assertSame(1, User::count());
    }

    /** Makes the first lookups miss, as they would before the winning callback committed. */
    private function missLookups(int $socialAccount, int $userByEmail): void
    {
        $this->app->instance(SocialAccountRepositoryInterface::class, new class($socialAccount) extends SocialAccountRepository
        {
            public function __construct(private int $misses) {}

            public function findByProvider(string $provider, string $providerId): ?SocialAccount
            {
                return $this->misses-- > 0 ? null : parent::findByProvider($provider, $providerId);
            }
        });

        $this->app->instance(UserRepositoryInterface::class, new class($userByEmail) extends UserRepository
        {
            public function __construct(private int $misses) {}

            public function findByEmail(string $email): ?User
            {
                return $this->misses-- > 0 ? null : parent::findByEmail($email);
            }
        });
    }

    public function test_a_state_issued_for_another_provider_is_rejected(): void
    {
        Socialite::shouldReceive('driver')->never();

        $response = $this->withUnencryptedCookie('oauth_state', 'google:abc')
            ->get('/api/auth/social/github/callback?state=google:abc');

        $this->assertFailure($response, 'invalid_state');
    }

    public function test_a_missing_state_cookie_is_rejected(): void
    {
        $this->assertFailure($this->get('/api/auth/social/github/callback?state=github:abc'), 'invalid_state');
    }

    public function test_the_state_is_checked_before_the_provider_error_and_the_configuration(): void
    {
        $this->withUnencryptedCookie('oauth_state', 'github:abc');

        $this->assertFailure($this->get('/api/auth/social/github/callback?state=github:zzz&error=access_denied'), 'invalid_state');

        config(['services.github.client_id' => 'CHANGE_ME']);
        $this->assertFailure($this->get('/api/auth/social/github/callback?state=github:zzz'), 'invalid_state');
    }

    public function test_a_declined_consent_is_not_reported(): void
    {
        Exceptions::fake();

        $this->assertFailure($this->hitSocialCallback('github', '&error=access_denied'), 'access_denied');

        Exceptions::assertNotReported(SocialLoginCancelled::class);
        Exceptions::assertNothingReported();
    }

    public function test_another_oauth_error_is_a_reported_provider_error(): void
    {
        Exceptions::fake();

        $this->assertFailure($this->hitSocialCallback('google', '&error=server_error'), 'provider_error');

        Exceptions::assertReported(fn (SocialProviderFailed $e): bool => str_contains($e->getMessage(), '"server_error"'));
    }

    public function test_an_oauth_error_that_is_not_a_plain_code_is_logged_as_unknown(): void
    {
        Exceptions::fake();

        $this->assertFailure($this->hitSocialCallback('google', '&error='.rawurlencode("x\nFAKE LOG LINE <script>")), 'provider_error');

        Exceptions::assertReported(fn (SocialProviderFailed $e): bool => str_contains($e->getMessage(), '"unknown"')
            && ! str_contains($e->getMessage(), 'FAKE'));
    }

    public function test_a_failed_provider_exchange_is_reported(): void
    {
        Exceptions::fake();
        Http::fake(['discord.com/api/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->assertFailure($this->hitSocialCallback('discord', '&code=used-code'), 'provider_error');

        Exceptions::assertReported(RequestException::class);
    }

    public function test_a_blank_provider_id_is_a_provider_error(): void
    {
        Exceptions::fake();
        $this->mockSocialite('github', $this->socialiteUser('', 'new@example.com'));

        $this->assertFailure($this->hitSocialCallback('github'), 'provider_error');

        Exceptions::assertReported(SocialProviderFailed::class);
        $this->assertSame(0, User::count());
    }

    public function test_a_blank_discord_id_is_a_provider_error(): void
    {
        $this->fakeDiscord(['id' => ' ', 'username' => 'ada', 'email' => 'ada@example.com', 'verified' => true]);

        $this->assertFailure($this->hitSocialCallback('discord', '&code=the-code'), 'provider_error');

        $this->assertSame(0, User::count());
    }

    public function test_an_unconfigured_provider_redirects_to_the_unavailable_page_instead_of_a_503(): void
    {
        config(['services.github.client_id' => 'CHANGE_ME']);

        $response = $this->get('/api/auth/social/github');

        $response->assertRedirect('http://front.test/auth/callback?status=error&reason=provider_unavailable');
        $this->assertTrue($response->getCookie('oauth_state', false)->isCleared());
    }

    public function test_a_misconfigured_provider_logs_which_key_without_its_value(): void
    {
        config(['services.github.client_secret' => 'CHANGE_ME-super-secret']);
        Log::spy();

        $this->get('/api/auth/social/github')
            ->assertRedirect('http://front.test/auth/callback?status=error&reason=provider_unavailable');

        Log::shouldHaveReceived('warning')->once()
            ->with('Social sign-in is not configured.', ['provider' => 'github', 'missing' => 'services.github.client_secret']);
    }

    public function test_a_provider_without_a_redirect_uri_counts_as_unconfigured(): void
    {
        config(['services.google.redirect' => null]);

        $this->get('/api/auth/social/google')
            ->assertRedirect('http://front.test/auth/callback?status=error&reason=provider_unavailable');
    }

    public function test_the_callback_of_an_unconfigured_provider_is_unavailable(): void
    {
        config(['services.discord.client_secret' => '']);
        Http::fake();

        $this->assertFailure($this->hitSocialCallback('discord', '&code=the-code'), 'provider_unavailable');

        Http::assertNothingSent();
    }

    public function test_the_state_cookie_is_httponly_lax_and_has_no_domain_locally(): void
    {
        config(['session.domain' => '.front.test']);

        $cookie = $this->get('/api/auth/social/discord')->getCookie('oauth_state', false);

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertStringStartsWith('discord:', $cookie->getValue());
    }

    public function test_in_production_the_state_cookie_is_a_secure_host_prefixed_cookie(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['session.domain' => '.front.test']);
        $this->fakeDiscord(['id' => '42', 'username' => 'ada', 'email' => 'ada@example.com', 'verified' => true]);

        $redirect = $this->get('/api/auth/social/discord');

        $this->assertNull($redirect->getCookie('oauth_state', false));
        $cookie = $redirect->getCookie('__Host-oauth_state', false);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());

        // The plain name is ignored there, so a cookie planted without the prefix does not pass.
        $state = $cookie->getValue();
        $this->withUnencryptedCookie('oauth_state', $state)
            ->get("/api/auth/social/discord/callback?state={$state}&code=the-code")
            ->assertRedirect('http://front.test/auth/callback?status=error&reason=invalid_state');

        $callback = $this->withUnencryptedCookies(['__Host-oauth_state' => $state])
            ->get("/api/auth/social/discord/callback?state={$state}&code=the-code")
            ->assertRedirect(self::SUCCESS);
        $this->assertTrue($callback->getCookie('__Host-oauth_state', false)->isCleared());
    }

    public function test_the_redirect_is_rate_limited_per_ip_with_a_redirect(): void
    {
        config(['services.github.client_id' => 'CHANGE_ME']);

        for ($i = 0; $i < 30; $i++) {
            $this->get('/api/auth/social/github')->assertRedirect();
        }

        $response = $this->get('/api/auth/social/github');
        $this->assertFailure($response, 'too_many_attempts');
        $response->assertHeader('Retry-After');
    }

    public function test_the_callback_is_rate_limited_per_ip_with_a_redirect(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/api/auth/social/github/callback')->assertRedirect();
        }

        $this->assertFailure($this->hitSocialCallback('github'), 'too_many_attempts');
    }
}
