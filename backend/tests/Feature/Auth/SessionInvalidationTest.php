<?php

namespace Tests\Feature\Auth;

use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Models\PasswordReset;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Revoking every session of an account raises `session_version`, which ends every access token issued with an older one. */
class SessionInvalidationTest extends TestCase
{
    use RefreshDatabase;
    use SignsInWithProviders;

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

    private function accessToken(User $user): string
    {
        return app(TokenFactory::class)->issueAccessToken($user)[0];
    }

    /** A token signed like ours but issued before the `sv` and `aud` claims existed. */
    private function legacyAccessToken(User $user): string
    {
        return JWT::encode([
            'iss' => config('jwt.issuer'),
            'sub' => (string) $user->id,
            'role' => $user->role,
            'iat' => now()->getTimestamp(),
            'exp' => now()->addMinutes(5)->getTimestamp(),
            'jti' => 'legacy',
        ], config('jwt.secret'), 'HS256');
    }

    private function googleSignIn(string $email): TestResponse
    {
        $this->configureSocialProviders();
        $this->mockSocialite('google', $this->socialiteUser('g1', $email, ['email_verified' => true]));

        return $this->hitSocialCallback('google')->assertRedirect('http://front.test/auth/callback?status=success');
    }

    public function test_a_squatter_loses_its_session_when_the_owner_takes_the_account_over(): void
    {
        // The squatter registered the owner's address: unverified, but signed in.
        $squatter = $this->postJson('/api/auth/register', [
            'name' => 'Squatter', 'email' => 'victim@example.com', 'password' => 'squat12345', 'password_confirmation' => 'squat12345',
        ])->assertCreated();
        $squatterAccess = $this->cookieValue($squatter, 'access_token');
        $this->assertNotNull($squatterAccess);

        $this->googleSignIn('victim@example.com');

        $owner = User::where('email', 'victim@example.com')->firstOrFail();
        $this->assertNotNull($owner->email_verified_at);
        $this->assertNull($owner->password);
        $this->assertSame(1, $owner->session_version);

        $this->withAccessToken($squatterAccess)
            ->putJson('/api/me/password/set', ['password' => 'mine12345', 'password_confirmation' => 'mine12345'])
            ->assertUnauthorized();
        $this->withAccessToken($squatterAccess)->getJson('/api/me')->assertUnauthorized();
        $this->withAccessToken($squatterAccess)->getJson('/api/auth/me')->assertUnauthorized();
        $this->assertNull($owner->fresh()->password);
    }

    public function test_the_owner_who_took_the_account_over_stays_signed_in(): void
    {
        User::factory()->unverified()->create(['email' => 'victim@example.com']);

        $callback = $this->googleSignIn('victim@example.com');

        $this->withAccessToken($this->cookieValue($callback, 'access_token'))->getJson('/api/me')->assertOk();
    }

    public function test_setting_a_password_keeps_this_device_and_ends_the_other_access_tokens(): void
    {
        $user = User::factory()->withoutPassword()->create();
        $otherDevice = $this->accessToken($user);
        $thisDevice = $this->accessToken($user);

        $response = $this->withAccessToken($thisDevice)
            ->putJson('/api/me/password/set', ['password' => 'mine12345', 'password_confirmation' => 'mine12345'])
            ->assertOk();

        $this->assertThisDeviceStaysSignedIn($response);
        $this->withAccessToken($otherDevice)->getJson('/api/me')->assertUnauthorized();
        $this->withAccessToken($thisDevice)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_changing_the_password_keeps_this_device_and_ends_the_other_access_tokens(): void
    {
        $user = User::factory()->create();
        $otherDevice = $this->accessToken($user);
        $thisDevice = $this->accessToken($user);

        $response = $this->withAccessToken($thisDevice)
            ->putJson('/api/me/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertOk();

        $this->assertThisDeviceStaysSignedIn($response);
        $this->withAccessToken($otherDevice)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_resetting_the_password_ends_the_access_tokens_issued_before(): void
    {
        $user = User::factory()->create();
        $before = $this->accessToken($user);
        PasswordReset::create(['email' => $user->email, 'token' => hash('sha256', 'goodtoken')]);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email, 'token' => 'goodtoken', 'password' => 'newsecret99', 'password_confirmation' => 'newsecret99',
        ])->assertOk();

        $this->withAccessToken($before)->getJson('/api/me')->assertUnauthorized();
        $this->withAccessToken($before)->getJson('/api/auth/me')->assertUnauthorized();
        $this->withAccessToken($this->accessToken($user->fresh()))->getJson('/api/me')->assertOk();
    }

    public function test_a_token_minted_in_the_same_second_but_before_the_revocation_is_refused(): void
    {
        $user = User::factory()->create();
        $this->freezeSecond();
        $mintedJustBefore = $this->accessToken($user);

        app(AuthServiceInterface::class)->revokeSessions($user);

        $this->withAccessToken($mintedJustBefore)->getJson('/api/me')->assertUnauthorized();
        $this->withAccessToken($this->accessToken($user->fresh()))->getJson('/api/me')->assertOk();
    }

    public function test_a_legacy_token_without_a_version_works_only_until_the_first_revocation(): void
    {
        config(['jwt.require_iss_aud' => false]);
        $user = User::factory()->create();
        $legacy = $this->legacyAccessToken($user);

        $this->withAccessToken($legacy)->getJson('/api/me')->assertOk();

        app(AuthServiceInterface::class)->revokeSessions($user);

        $this->withAccessToken($legacy)->getJson('/api/me')->assertUnauthorized();
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
