<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Remember me" (`remember` on POST /api/auth/login, default true). Remembered: cookies that expire with their
 * tokens, a refresh token sliding `jwt.refresh_ttl_days` up to `jwt.refresh_family_max_days`. Not remembered: session
 * cookies, a refresh token sliding `jwt.refresh_session_hours` up to `jwt.refresh_session_family_max_days`.
 */
class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'jwt.access_ttl' => 15,
            'jwt.refresh_ttl_days' => 30,
            'jwt.refresh_family_max_days' => 90,
            'jwt.refresh_session_hours' => 24,
            'jwt.refresh_session_family_max_days' => 7,
        ]);
        Carbon::setTestNow(now()->startOfSecond());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $extra */
    private function login(array $extra = []): TestResponse
    {
        $user = User::factory()->create(['email' => 'a@example.com']);

        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password', ...$extra])->assertOk();
    }

    private function refreshWith(string $plain): TestResponse
    {
        return $this->withCredentials()->withUnencryptedCookie('refresh_token', $plain)->postJson('/api/auth/refresh');
    }

    private function assertSessionCookies(TestResponse $response): void
    {
        $this->assertSame(0, $response->getCookie('access_token', false)->getExpiresTime());
        $this->assertSame(0, $response->getCookie('refresh_token', false)->getExpiresTime());
    }

    private function tokenRow(TestResponse $response): RefreshToken
    {
        return RefreshToken::where('token_hash', hash('sha256', $response->getCookie('refresh_token', false)->getValue()))->sole();
    }

    public function test_without_remember_me_both_cookies_are_session_cookies_and_the_token_lasts_a_day(): void
    {
        $response = $this->login(['remember' => false]);

        $this->assertSessionCookies($response);
        $row = $this->tokenRow($response);
        $this->assertFalse($row->remember);
        $this->assertTrue(now()->addHours(24)->equalTo($row->expires_at));
    }

    public function test_remember_me_keeps_cookies_that_expire_with_their_tokens(): void
    {
        $response = $this->login(['remember' => true]);

        $this->assertSame(now()->addMinutes(15)->getTimestamp(), $response->getCookie('access_token', false)->getExpiresTime());
        $this->assertSame(now()->addDays(30)->getTimestamp(), $response->getCookie('refresh_token', false)->getExpiresTime());
        $this->assertTrue($this->tokenRow($response)->remember);
    }

    public function test_a_login_without_the_field_is_remembered(): void
    {
        $response = $this->login();

        $this->assertSame(now()->addDays(30)->getTimestamp(), $response->getCookie('refresh_token', false)->getExpiresTime());
        $this->assertTrue($this->tokenRow($response)->remember);
    }

    public function test_remember_must_be_a_boolean(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'password', 'remember' => 'sometimes'])
            ->assertUnprocessable()->assertJsonValidationErrors('remember');
    }

    public function test_rotation_keeps_the_choice_and_slides_the_short_window(): void
    {
        $first = $this->login(['remember' => false]);
        $this->travel(5)->hours();

        $rotated = $this->refreshWith($first->getCookie('refresh_token', false)->getValue())->assertOk();

        $this->assertSessionCookies($rotated);
        $row = $this->tokenRow($rotated);
        $this->assertFalse($row->remember);
        $this->assertTrue(now()->addHours(24)->equalTo($row->expires_at));
    }

    public function test_a_graced_sibling_keeps_the_choice_too(): void
    {
        $first = $this->login(['remember' => false]);
        $old = $first->getCookie('refresh_token', false)->getValue();
        $this->refreshWith($old)->assertOk();

        // Second tab, same user agent, within the grace window: a sibling in the same family.
        $sibling = $this->refreshWith($old)->assertOk();

        $this->assertSessionCookies($sibling);
        $this->assertFalse($this->tokenRow($sibling)->remember);
        $this->assertSame(0, RefreshToken::where('remember', true)->count());
    }

    public function test_a_session_login_ends_after_seven_days_however_often_it_refreshes(): void
    {
        $user = User::factory()->create();
        $plain = bin2hex(random_bytes(16));
        RefreshToken::factory()->create([
            'user_id' => $user->id, 'token_hash' => hash('sha256', $plain), 'remember' => false,
            'family_started_at' => now()->subDays(7)->addHour(), 'expires_at' => now()->addHours(20),
        ]);

        $rotated = $this->refreshWith($plain)->assertOk();
        // Capped by the family's end (one hour left), not the 24-hour window.
        $this->assertTrue(now()->addHour()->equalTo($this->tokenRow($rotated)->expires_at));

        $this->travel(61)->minutes();
        $this->refreshWith($rotated->getCookie('refresh_token', false)->getValue())->assertUnauthorized();
    }

    public function test_a_remembered_login_still_ends_after_ninety_days(): void
    {
        $user = User::factory()->create();
        $plain = bin2hex(random_bytes(16));
        RefreshToken::factory()->create([
            'user_id' => $user->id, 'token_hash' => hash('sha256', $plain), 'remember' => true,
            'family_started_at' => now()->subDays(8), 'expires_at' => now()->addDays(20),
        ]);

        // Past the session cap (7 days), well within the remembered one (90 days).
        $rotated = $this->refreshWith($plain)->assertOk();
        $this->assertTrue(now()->addDays(30)->equalTo($this->tokenRow($rotated)->expires_at));

        $old = bin2hex(random_bytes(16));
        RefreshToken::factory()->create([
            'user_id' => $user->id, 'token_hash' => hash('sha256', $old), 'remember' => true,
            'family_started_at' => now()->subDays(91), 'expires_at' => now()->addDay(),
        ]);
        $this->refreshWith($old)->assertUnauthorized();
    }

    public function test_changing_the_password_keeps_a_session_login_a_session_login(): void
    {
        $login = $this->login(['remember' => false]);
        $access = $login->getCookie('access_token', false)->getValue();

        $changed = $this->withToken($access)->putJson('/api/me/password', [
            'current_password' => 'password', 'password' => 'newsecret99', 'password_confirmation' => 'newsecret99',
        ])->assertOk();

        $this->assertSessionCookies($changed);
        $this->assertFalse($this->tokenRow($changed)->remember);
    }

    public function test_changing_the_password_keeps_a_remembered_login_remembered(): void
    {
        $login = $this->login();
        $access = $login->getCookie('access_token', false)->getValue();

        $changed = $this->withToken($access)->putJson('/api/me/password', [
            'current_password' => 'password', 'password' => 'newsecret99', 'password_confirmation' => 'newsecret99',
        ])->assertOk();

        $this->assertSame(now()->addDays(30)->getTimestamp(), $changed->getCookie('refresh_token', false)->getExpiresTime());
        $this->assertTrue($this->tokenRow($changed)->remember);
    }

    public function test_registration_is_remembered(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret1234', 'password_confirmation' => 'secret1234',
        ])->assertCreated();

        $this->assertSame(now()->addDays(30)->getTimestamp(), $response->getCookie('refresh_token', false)->getExpiresTime());
        $this->assertTrue($this->tokenRow($response)->remember);
    }
}
