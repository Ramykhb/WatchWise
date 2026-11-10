<?php

namespace Tests\Feature\Auth;

use App\Auth\Factories\TokenFactory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/** Revoking every session of an account (`sessions_invalidated_at`) also ends access tokens issued before it. */
class SessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.frontend_url' => 'http://front.test']);
        Mail::fake();
    }

    private function cookieValue(TestResponse $response, string $name): ?string
    {
        return $response->getCookie($name, false)?->getValue();
    }

    private function withAccessToken(string $jwt): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$jwt);
    }

    /** An access token issued a few seconds ago, before whatever the test does next. */
    private function earlierAccessToken(User $user): string
    {
        $this->travelTo(now()->subSeconds(10));
        [$jwt] = app(TokenFactory::class)->issueAccessToken($user);
        $this->travelBack();

        return $jwt;
    }

    private function googleSignIn(string $email): void
    {
        $socialUser = new SocialiteUser;
        $socialUser->map(['id' => 'g1', 'name' => 'Owner', 'nickname' => 'owner', 'email' => $email, 'avatar' => null]);
        $socialUser->token = 'provider-access-token';
        $socialUser->user = ['email_verified' => true];

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->withUnencryptedCookie('oauth_state', 'abc')
            ->get('/api/auth/social/google/callback?state=abc')
            ->assertRedirect('http://front.test/auth/callback?status=success');
    }

    public function test_a_squatter_loses_its_session_when_the_owner_takes_the_account_over(): void
    {
        // The squatter registers the owner's address (unverified, but signed in) a little earlier.
        $this->travelTo(now()->subSeconds(10));
        $squatter = $this->postJson('/api/auth/register', [
            'name' => 'Squatter', 'email' => 'victim@example.com', 'password' => 'squat12345', 'password_confirmation' => 'squat12345',
        ])->assertCreated();
        $this->travelBack();
        $squatterAccess = $this->cookieValue($squatter, 'access_token');
        $this->assertNotNull($squatterAccess);

        $this->googleSignIn('victim@example.com');

        $owner = User::where('email', 'victim@example.com')->firstOrFail();
        $this->assertNotNull($owner->email_verified_at);
        $this->assertNull($owner->password);
        $this->assertNotNull($owner->sessions_invalidated_at);

        $this->withAccessToken($squatterAccess)
            ->putJson('/api/me/password/set', ['password' => 'mine12345', 'password_confirmation' => 'mine12345'])
            ->assertUnauthorized();
        $this->withAccessToken($squatterAccess)->getJson('/api/me')->assertUnauthorized();
        $this->withAccessToken($squatterAccess)->getJson('/api/auth/me')->assertUnauthorized();
        $this->assertNull($owner->fresh()->password);
    }

    public function test_setting_a_password_keeps_this_device_and_ends_older_access_tokens(): void
    {
        $user = User::factory()->withoutPassword()->create();
        $earlier = $this->earlierAccessToken($user);
        [$current] = app(TokenFactory::class)->issueAccessToken($user);

        $response = $this->withAccessToken($current)
            ->putJson('/api/me/password/set', ['password' => 'mine12345', 'password_confirmation' => 'mine12345'])
            ->assertOk();

        $this->assertThisDeviceStaysSignedIn($response);
        $this->withAccessToken($earlier)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_changing_the_password_keeps_this_device_and_ends_older_access_tokens(): void
    {
        $user = User::factory()->create();
        $earlier = $this->earlierAccessToken($user);
        [$current] = app(TokenFactory::class)->issueAccessToken($user);

        $response = $this->withAccessToken($current)
            ->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertOk();

        $this->assertThisDeviceStaysSignedIn($response);
        $this->withAccessToken($earlier)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_tokens_issued_after_the_last_revocation_keep_working(): void
    {
        $user = User::factory()->create(['sessions_invalidated_at' => now()->subMinute()]);
        [$jwt] = app(TokenFactory::class)->issueAccessToken($user);

        $this->withAccessToken($jwt)->getJson('/api/me')->assertOk();
    }

    /** The fresh cookies work, and so does rotating the fresh refresh token. */
    private function assertThisDeviceStaysSignedIn(TestResponse $response): void
    {
        $access = $this->cookieValue($response, 'access_token');
        $refresh = $this->cookieValue($response, 'refresh_token');
        $this->assertNotNull($access);
        $this->assertNotNull($refresh);

        $this->withAccessToken($access)->getJson('/api/me')->assertOk();
        $rotated = $this->withCredentials()->withUnencryptedCookie('refresh_token', $refresh)->postJson('/api/auth/refresh')->assertOk();
        $this->withAccessToken($this->cookieValue($rotated, 'access_token'))->getJson('/api/me')->assertOk();
    }
}
