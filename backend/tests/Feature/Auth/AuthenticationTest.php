<?php

namespace Tests\Feature\Auth;

use App\Auth\TokenFactory;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
        return $this->withUnencryptedCookie('refresh_token', $token)->postJson('/api/auth/refresh');
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
        $this->assertSame('user', $claims->role);
        $this->assertSame(900, $claims->exp - $claims->iat);
        $this->assertNotEmpty($claims->jti);

        $this->withUnencryptedCookie('access_token', $jwt)->getJson('/api/auth/me')
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

        $this->withHeader('Authorization', 'Bearer '.$jwt.'x')->getJson('/api/auth/me')->assertUnauthorized();

        $this->travel(16)->minutes();
        $this->withHeader('Authorization', 'Bearer '.$jwt)->getJson('/api/auth/me')->assertUnauthorized();
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

        $response = $this->withUnencryptedCookie('refresh_token', $refresh)->postJson('/api/auth/logout')->assertOk();

        $this->assertTrue($response->getCookie('access_token', false)->isCleared());
        $this->assertTrue($response->getCookie('refresh_token', false)->isCleared());
        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
        $this->refreshWith($refresh)->assertUnauthorized();
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'x'])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'x'])->assertStatus(429);
    }
}
