<?php

namespace Tests\Feature\Auth;

use App\Auth\Factories\TokenFactory;
use App\Auth\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every signed-in route outside /api/auth needs a verified email; /api/auth stays open so an unverified
 * member can check their session, resend the link, refresh and sign out.
 */
class RequireVerifiedEmailTest extends TestCase
{
    use RefreshDatabase;

    private const UNVERIFIED_BODY = ['message' => 'Verify your email address to continue.', 'code' => 'email_unverified'];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'broadcasting.connections.reverb.key' => 'test-socket-key',
            'broadcasting.connections.reverb.secret' => 'test-socket-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
    }

    private function as(User $user): static
    {
        [$jwt] = app(TokenFactory::class)->issueAccessToken($user);

        return $this->withHeader('Authorization', 'Bearer '.$jwt);
    }

    /**
     * One route per signed-in area. `{id}` is replaced with the caller's id.
     *
     * @return array<string, array{string, string, array<string, string>, string}>
     */
    public static function signedInRoutes(): array
    {
        return [
            'social' => ['GET', '/api/watchlist', [], 'user'],
            'admin' => ['GET', '/api/admin/users', [], 'admin'],
            'chat' => ['GET', '/api/chat/connection', [], 'user'],
            'recommendations' => ['GET', '/api/taste-profile', [], 'user'],
            'rooms socket-auth' => ['POST', '/api/rooms/socket-auth', ['socket_id' => '123.456', 'channel_name' => 'private-rooms.user.{id}'], 'user'],
            'notifications socket-auth' => ['POST', '/api/notifications/socket-auth', ['socket_id' => '123.456', 'channel_name' => 'private-notifications.{id}'], 'user'],
        ];
    }

    /** @param array<string, string> $body */
    private function callAs(?User $user, string $method, string $uri, array $body)
    {
        $body = array_map(fn (string $value): string => str_replace('{id}', (string) $user?->id, $value), $body);

        return ($user ? $this->as($user) : $this)->json($method, $uri, $body);
    }

    /** @param array<string, string> $body */
    #[DataProvider('signedInRoutes')]
    public function test_guests_are_unauthenticated(string $method, string $uri, array $body): void
    {
        $this->callAs(null, $method, $uri, $body)->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }

    /** @param array<string, string> $body */
    #[DataProvider('signedInRoutes')]
    public function test_unverified_members_are_told_to_verify_their_email(string $method, string $uri, array $body, string $role): void
    {
        $user = User::factory()->unverified()->create(['role' => $role]);

        $this->callAs($user, $method, $uri, $body)->assertForbidden()->assertExactJson(self::UNVERIFIED_BODY);
    }

    /** @param array<string, string> $body */
    #[DataProvider('signedInRoutes')]
    public function test_verified_members_are_let_through(string $method, string $uri, array $body, string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->callAs($user, $method, $uri, $body)->assertOk();
    }

    public function test_the_verification_check_runs_before_rate_limiting(): void
    {
        $user = User::factory()->unverified()->create();

        $this->as($user)->postJson('/api/rooms/socket-auth', ['socket_id' => '123.456', 'channel_name' => 'private-rooms.public'])
            ->assertForbidden()
            ->assertHeaderMissing('X-RateLimit-Limit');
    }

    public function test_an_unverified_member_can_still_use_the_auth_routes(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create(['email' => 'new@example.com']);

        $login = $this->postJson('/api/auth/login', ['email' => 'new@example.com', 'password' => 'password'])->assertOk();
        $access = $login->getCookie('access_token', false)->getValue();
        $refresh = $login->getCookie('refresh_token', false)->getValue();

        $this->withToken($access)->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.email_verified_at', null);
        $this->postJson('/api/auth/resend-verification', ['email' => 'new@example.com'])->assertOk();
        Mail::assertSent(VerifyEmailMail::class, fn (VerifyEmailMail $mail): bool => $mail->hasTo($user->email));

        $refreshed = $this->withCredentials()->withUnencryptedCookie('refresh_token', $refresh)->postJson('/api/auth/refresh')->assertOk();
        $this->withCredentials()->withUnencryptedCookie('refresh_token', $refreshed->getCookie('refresh_token', false)->getValue())
            ->postJson('/api/auth/logout')->assertOk();
    }

    public function test_public_routes_stay_open_to_guests(): void
    {
        $this->getJson('/api/health')->assertOk();
        $this->getJson('/api/documentation/openapi.json')->assertOk();
    }

    public function test_every_signed_in_route_outside_auth_requires_a_verified_email(): void
    {
        $signedIn = 0;

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            if (! in_array('auth.jwt', $middleware, true)) {
                continue;
            }

            $signedIn++;

            if (str_starts_with($route->uri(), 'api/auth/')) {
                continue;
            }

            $this->assertContains('verified.email', $middleware, "/{$route->uri()} is signed-in but does not use `verified.email`.");
        }

        $this->assertGreaterThan(50, $signedIn);
    }

    public function test_channel_auth_is_only_reachable_through_the_verified_socket_routes(): void
    {
        $user = User::factory()->unverified()->create();

        $this->as($user)->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => "private-notifications.{$user->id}"])
            ->assertNotFound();
    }
}
