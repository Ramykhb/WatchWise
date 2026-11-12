<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The token cookies: Secure outside local/testing, and each one lives as long as its token. */
class AuthCookiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.access_ttl' => 15, 'jwt.refresh_ttl_days' => 30, 'jwt.refresh_family_max_days' => 90]);
        Carbon::setTestNow(now()->startOfSecond());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function login(): TestResponse
    {
        $user = User::factory()->create();

        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_cookies_are_secure_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $response = $this->login();

        $this->assertTrue($response->getCookie('access_token', false)->isSecure());
        $this->assertTrue($response->getCookie('refresh_token', false)->isSecure());
    }

    public function test_cookies_are_not_secure_in_testing_so_plain_http_works_locally(): void
    {
        $response = $this->login();

        $this->assertFalse($response->getCookie('access_token', false)->isSecure());
        $this->assertFalse($response->getCookie('refresh_token', false)->isSecure());
    }

    public function test_each_cookie_expires_with_its_token(): void
    {
        $response = $this->login();

        $this->assertSame(now()->addMinutes(15)->getTimestamp(), $response->getCookie('access_token', false)->getExpiresTime());
        $this->assertSame(now()->addDays(30)->getTimestamp(), $response->getCookie('refresh_token', false)->getExpiresTime());
    }

    public function test_the_refresh_cookie_ends_with_the_family_when_that_comes_first(): void
    {
        $plain = bin2hex(random_bytes(16));
        $user = User::factory()->create();
        RefreshToken::factory()->create([
            'user_id' => $user->id, 'token_hash' => hash('sha256', $plain),
            'family_started_at' => now()->subDays(80), 'expires_at' => now()->addDays(10),
        ]);

        $response = $this->withCredentials()->withUnencryptedCookie('refresh_token', $plain)->postJson('/api/auth/refresh')->assertOk();

        $this->assertSame(now()->addDays(10)->getTimestamp(), $response->getCookie('refresh_token', false)->getExpiresTime());
    }
}
