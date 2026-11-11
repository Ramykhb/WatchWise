<?php

namespace Tests\Feature\Auth;

use App\Auth\Factories\TokenFactory;
use App\Auth\Services\AuthService;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function cookieValue($response, string $name): ?string
    {
        return $response->getCookie($name, false)?->getValue();
    }

    private function refreshWith(string $token)
    {
        return $this->withCredentials()->withUnencryptedCookie('refresh_token', $token)->postJson('/api/auth/refresh');
    }

    public function test_user_can_register_and_receives_httponly_cookies(): void
    {
        Event::fake([Registered::class]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertCreated()->assertJsonPath('user.email', 'jane@example.com')
            ->assertJsonPath('user.role', 'user')
            ->assertJsonMissingPath('user.password');

        $access = $response->getCookie('access_token', false);
        $refresh = $response->getCookie('refresh_token', false);
        $this->assertTrue($access->isHttpOnly());
        $this->assertTrue($refresh->isHttpOnly());
        $this->assertSame('lax', $access->getSameSite());
        $this->assertSame('/api/auth', $refresh->getPath());

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'role' => 'user', 'email_verified_at' => null]);
        $this->assertDatabaseMissing('refresh_tokens', ['token_hash' => $refresh->getValue()]);
        $this->assertDatabaseHas('refresh_tokens', ['token_hash' => hash('sha256', $refresh->getValue())]);
        Event::assertDispatched(Registered::class);
    }

    public function test_register_validates_input_and_cannot_set_role(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'X', 'email' => 'taken@example.com', 'password' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);

        $this->postJson('/api/auth/register', [
            'name' => 'Evil', 'email' => 'evil@example.com', 'role' => 'admin',
            'password' => 'secret1234', 'password_confirmation' => 'secret1234',
        ])->assertCreated()->assertJsonPath('user.role', 'user');
    }

    public function test_login_sets_cookies_and_me_works_with_cookie_and_bearer(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);

        $login = $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.id', $user->id);

        $jwt = $this->cookieValue($login, 'access_token');
        $claims = app(TokenFactory::class)->parseAccessToken($jwt);
        $this->assertSame((string) $user->id, $claims->sub);
        $this->assertSame(config('jwt.issuer'), $claims->iss);
        $this->assertSame(config('jwt.audience'), $claims->aud);
        $this->assertSame(900, $claims->exp - $claims->iat);
        $this->assertObjectNotHasProperty('role', $claims);
        $this->assertObjectNotHasProperty('jti', $claims);

        $this->withCredentials()->withUnencryptedCookie('access_token', $jwt)->getJson('/api/auth/me')
            ->assertOk()->assertJsonPath('user.id', $user->id);

        $this->withHeader('Authorization', 'Bearer '.$jwt)->getJson('/api/auth/me')
            ->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_login_rejects_bad_credentials_and_inactive_users(): void
    {
        User::factory()->create(['email' => 'a@example.com']);
        User::factory()->create(['email' => 'off@example.com', 'is_active' => false]);

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'password'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'off@example.com', 'password' => 'password'])->assertUnauthorized();
    }

    public function test_me_requires_a_valid_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer garbage')->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_tampered_and_expired_access_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        [$jwt] = app(TokenFactory::class)->issueAccessToken($user);

        [$header, $payload, $signature] = explode('.', $jwt);
        $flipped = ($signature[5] === 'A' ? 'B' : 'A');
        $tampered = implode('.', [$header, $payload, substr_replace($signature, $flipped, 5, 1)]);
        $this->withHeader('Authorization', 'Bearer '.$tampered)->getJson('/api/auth/me')->assertUnauthorized();

        config(['jwt.access_ttl' => -1]);
        [$expired] = app(TokenFactory::class)->issueAccessToken($user);
        $this->withHeader('Authorization', 'Bearer '.$expired)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_refresh_rotates_the_token(): void
    {
        User::factory()->create(['email' => 'a@example.com']);
        $login = $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'password']);
        $first = $this->cookieValue($login, 'refresh_token');

        $refreshed = $this->refreshWith($first)->assertOk();
        $second = $this->cookieValue($refreshed, 'refresh_token');

        $this->assertNotSame($first, $second);
        $this->assertNotNull($this->cookieValue($refreshed, 'access_token'));

        $old = RefreshToken::where('token_hash', hash('sha256', $first))->first();
        $new = RefreshToken::where('token_hash', hash('sha256', $second))->first();
        $this->assertNotNull($old->revoked_at);
        $this->assertSame($new->id, $old->replaced_by);
        $this->assertSame($old->family_id, $new->family_id);
        $this->assertNull($new->revoked_at);
    }

    public function test_reusing_a_rotated_refresh_token_revokes_the_whole_family(): void
    {
        User::factory()->create(['email' => 'a@example.com']);
        $login = $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'password']);
        $first = $this->cookieValue($login, 'refresh_token');
        $second = $this->cookieValue($this->refreshWith($first)->assertOk(), 'refresh_token');

        $this->travel(config('jwt.refresh_reuse_grace_seconds') + 1)->seconds();
        $this->refreshWith($first)->assertUnauthorized();

        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
        $this->refreshWith($second)->assertUnauthorized();
    }

    public function test_refresh_fails_without_unknown_or_expired_token(): void
    {
        $this->postJson('/api/auth/refresh')->assertUnauthorized();
        $this->refreshWith('unknown')->assertUnauthorized();

        $user = User::factory()->create();
        $pair = app(TokenFactory::class)->issue($user, request());
        $this->travel(31)->days();
        $this->refreshWith($pair->refreshToken)->assertUnauthorized();
    }

    public function test_logout_revokes_the_family_and_clears_cookies(): void
    {
        User::factory()->create(['email' => 'a@example.com']);
        $login = $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'password']);
        $refresh = $this->cookieValue($login, 'refresh_token');

        $response = $this->withCredentials()->withUnencryptedCookie('refresh_token', $refresh)->postJson('/api/auth/logout')->assertOk();

        $this->assertTrue($response->getCookie('access_token', false)->isCleared());
        $this->assertTrue($response->getCookie('refresh_token', false)->isCleared());
        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
        $this->refreshWith($refresh)->assertUnauthorized();
    }

    public function test_a_login_upgrades_a_hash_made_with_an_older_cost(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => 'secret1234']);
        $this->assertSame(4, Hash::info($user->password)['options']['cost']);
        Hash::driver('bcrypt')->setRounds(5);

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'secret1234'])->assertOk();

        $stored = $user->fresh()->password;
        $this->assertSame(5, Hash::info($stored)['options']['cost']);
        $this->assertTrue(Hash::check('secret1234', $stored));
        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'secret1234'])->assertOk();
    }

    public function test_the_upgrade_waits_while_a_link_bound_to_the_hash_is_pending(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => 'secret1234']);
        $user->forceFill(['deletion_nonce' => 'n', 'deletion_requested_at' => now()])->save();
        $before = $user->fresh()->password;
        Hash::driver('bcrypt')->setRounds(5);

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'secret1234'])->assertOk();

        $this->assertSame($before, $user->fresh()->password);
        $this->assertSame('n', $user->fresh()->deletion_nonce);
    }

    /** @param  array<string, mixed>  $body */
    private function loginFrom(string $ip, array $body): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/auth/login', $body);
    }

    public function test_login_throttle_cannot_be_bypassed_by_rotating_the_email(): void
    {
        // Every request counts in the per-IP bucket (20 per minute), whatever email it names.
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/auth/login', ['email' => "user{$i}@example.com", 'password' => 'x'])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', ['email' => 'fresh@example.com', 'password' => 'x'])->assertTooManyRequests();
    }

    public function test_failed_logins_for_one_account_from_one_ip_are_throttled(): void
    {
        for ($i = 0; $i < AuthService::LOGIN_FAILURES_PER_MINUTE; $i++) {
            $this->loginFrom('10.0.0.1', ['email' => 'Victim@Example.com ', 'password' => 'x'])->assertUnauthorized();
        }

        $this->loginFrom('10.0.0.1', ['email' => 'victim@example.com', 'password' => 'x'])->assertTooManyRequests();
        // Another address from the same IP still gets its own failure bucket.
        $this->loginFrom('10.0.0.1', ['email' => 'other@example.com', 'password' => 'x'])->assertUnauthorized();
    }

    public function test_failures_from_other_ips_do_not_lock_the_owner_out(): void
    {
        User::factory()->create(['email' => 'victim@example.com', 'password' => 'secret1234']);

        // A distributed attacker fills every bucket it can reach for the victim's address...
        for ($ip = 1; $ip <= 3; $ip++) {
            for ($i = 0; $i < AuthService::LOGIN_FAILURES_PER_MINUTE; $i++) {
                $this->loginFrom("203.0.113.{$ip}", ['email' => 'victim@example.com', 'password' => 'wrong'])->assertUnauthorized();
            }
            $this->loginFrom("203.0.113.{$ip}", ['email' => 'victim@example.com', 'password' => 'wrong'])->assertTooManyRequests();
        }

        // ...but the owner, from their own IP, signs in at once.
        $this->loginFrom('198.51.100.7', ['email' => 'victim@example.com', 'password' => 'secret1234'])->assertOk();
    }

    public function test_a_successful_login_clears_the_failure_counter(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => 'secret1234']);

        for ($i = 0; $i < AuthService::LOGIN_FAILURES_PER_MINUTE - 1; $i++) {
            $this->loginFrom('10.0.0.1', ['email' => 'a@example.com', 'password' => 'typo'])->assertUnauthorized();
        }
        $this->loginFrom('10.0.0.1', ['email' => 'a@example.com', 'password' => 'secret1234'])->assertOk();

        // The counter started again: as many failures as the limit allows are answered 401 before a 429.
        for ($i = 0; $i < AuthService::LOGIN_FAILURES_PER_MINUTE; $i++) {
            $this->loginFrom('10.0.0.1', ['email' => 'a@example.com', 'password' => 'typo'])->assertUnauthorized();
        }
        $this->loginFrom('10.0.0.1', ['email' => 'a@example.com', 'password' => 'typo'])->assertTooManyRequests();
    }

    public function test_addresses_of_one_ipv6_64_share_the_per_ip_bucket(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->loginFrom('2001:db8:1:2::'.dechex($i), ['email' => "u{$i}@example.com", 'password' => 'x'])->assertUnauthorized();
        }

        $this->loginFrom('2001:db8:1:2:ffff::99', ['email' => 'z@example.com', 'password' => 'x'])->assertTooManyRequests();
        $this->loginFrom('2001:db8:1:3::1', ['email' => 'z@example.com', 'password' => 'x'])->assertUnauthorized();
    }

    public function test_addresses_of_one_ipv6_64_share_the_failure_bucket(): void
    {
        for ($i = 1; $i <= AuthService::LOGIN_FAILURES_PER_MINUTE; $i++) {
            $this->loginFrom('2001:db8:1:2::'.dechex($i), ['email' => 'victim@example.com', 'password' => 'x'])->assertUnauthorized();
        }

        $this->loginFrom('2001:db8:1:2::abcd', ['email' => 'victim@example.com', 'password' => 'x'])->assertTooManyRequests();
    }

    public function test_the_failure_key_is_short_whatever_the_email(): void
    {
        $email = str_repeat('a', 243).'@example.com';
        $this->assertSame(255, strlen($email));

        $this->loginFrom('10.0.0.1', ['email' => $email, 'password' => 'x'])->assertUnauthorized();

        $key = AuthService::loginFailuresKey($email, '10.0.0.1');
        $this->assertSame('auth-login:'.hash('sha256', $email.'|10.0.0.1'), $key);
        $this->assertSame(1, RateLimiter::attempts($key));
    }

    public function test_the_failure_429_looks_like_the_route_limiters_429(): void
    {
        for ($i = 0; $i < AuthService::LOGIN_FAILURES_PER_MINUTE; $i++) {
            $this->loginFrom('10.0.0.1', ['email' => 'a@example.com', 'password' => 'x']);
        }
        $failures = $this->loginFrom('10.0.0.1', ['email' => 'a@example.com', 'password' => 'x'])->assertTooManyRequests();

        for ($i = 0; $i < 20; $i++) {
            $this->loginFrom('10.0.0.2', ['email' => "u{$i}@example.com", 'password' => 'x']);
        }
        $perIp = $this->loginFrom('10.0.0.2', ['email' => 'z@example.com', 'password' => 'x'])->assertTooManyRequests();

        $this->assertSame('Too Many Attempts.', $failures->json('message'));
        $this->assertSame($perIp->json('message'), $failures->json('message'));
        foreach (['Retry-After', 'X-RateLimit-Reset', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'] as $header) {
            $failures->assertHeader($header);
            $perIp->assertHeader($header);
        }
        $this->assertGreaterThan(0, (int) $failures->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(60, (int) $failures->headers->get('Retry-After'));
    }
}
