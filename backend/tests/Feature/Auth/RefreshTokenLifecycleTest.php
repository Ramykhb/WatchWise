<?php

namespace Tests\Feature\Auth;

use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Rotation rules: the reuse grace window, the family's absolute lifetime and the cases that revoke the family. */
class RefreshTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'jwt.refresh_ttl_days' => 30,
            'jwt.refresh_family_max_days' => 90,
            'jwt.refresh_reuse_grace_seconds' => 20,
        ]);
        Carbon::setTestNow(now()->startOfSecond());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function login(User $user): string
    {
        return $this->cookieValue($this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk());
    }

    private function refreshWith(string $token): TestResponse
    {
        return $this->withCredentials()->withUnencryptedCookie('refresh_token', $token)->postJson('/api/auth/refresh');
    }

    private function cookieValue(TestResponse $response): string
    {
        return $response->getCookie('refresh_token', false)->getValue();
    }

    private function row(string $plain): RefreshToken
    {
        return RefreshToken::where('token_hash', hash('sha256', $plain))->firstOrFail();
    }

    /** A stored token in the given state whose plain value is returned. */
    private function storedToken(array $attributes): string
    {
        $plain = bin2hex(random_bytes(16));
        RefreshToken::factory()->create(['token_hash' => hash('sha256', $plain)] + $attributes);

        return $plain;
    }

    public function test_the_same_token_refreshed_twice_inside_the_grace_window_gives_two_working_sessions_once(): void
    {
        $first = $this->login(User::factory()->create());

        $second = $this->cookieValue($this->refreshWith($first)->assertOk());
        $this->travel(5)->seconds();
        $sibling = $this->cookieValue($this->refreshWith($first)->assertOk());

        $this->assertNotSame($second, $sibling);
        $this->assertSame($this->row($second)->id, $this->row($first)->replaced_by);
        $this->assertSame($this->row($first)->family_id, $this->row($sibling)->family_id);
        $this->assertNull($this->row($second)->revoked_at);
        $this->assertNull($this->row($sibling)->revoked_at);
        $this->assertTrue($this->row($first)->grace_used_at->eq(now()));

        $this->refreshWith($second)->assertOk();
        $this->refreshWith($sibling)->assertOk();
    }

    public function test_a_second_grace_on_the_same_token_revokes_the_family(): void
    {
        $first = $this->login(User::factory()->create());
        $this->refreshWith($first)->assertOk();
        $this->refreshWith($first)->assertOk();

        $this->refreshWith($first)->assertUnauthorized();

        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
    }

    public function test_reuse_from_another_user_agent_inside_the_window_revokes_the_family(): void
    {
        $first = $this->login(User::factory()->create());
        $second = $this->cookieValue($this->refreshWith($first)->assertOk());

        $this->withHeader('User-Agent', 'Another browser')->refreshWith($first)->assertUnauthorized();

        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
        $this->refreshWith($second)->assertUnauthorized();
    }

    public function test_reuse_after_the_successor_already_rotated_revokes_the_family(): void
    {
        $first = $this->login(User::factory()->create());
        $second = $this->cookieValue($this->refreshWith($first)->assertOk());
        $third = $this->cookieValue($this->refreshWith($second)->assertOk());

        $this->refreshWith($first)->assertUnauthorized();

        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
        $this->refreshWith($third)->assertUnauthorized();
    }

    public function test_a_grace_is_refused_once_the_family_is_past_its_absolute_lifetime(): void
    {
        $user = User::factory()->create();
        $family = ['user_id' => $user->id, 'family_id' => 'old', 'family_started_at' => now()->subDays(91), 'user_agent' => 'Symfony'];
        $successor = RefreshToken::factory()->create($family + ['expires_at' => now()->addDays(10)]);
        $rotated = $this->storedToken($family + ['expires_at' => now()->addDays(10), 'revoked_at' => now()->subSeconds(5), 'replaced_by' => $successor->id]);

        $this->refreshWith($rotated)->assertUnauthorized();

        $this->assertNotNull($successor->fresh()->revoked_at);
        $this->assertNull($this->row($rotated)->grace_used_at);
        $this->assertSame(2, RefreshToken::count());
    }

    public function test_reuse_after_the_grace_window_revokes_the_family(): void
    {
        $first = $this->login(User::factory()->create());
        $second = $this->cookieValue($this->refreshWith($first)->assertOk());

        $this->travel(21)->seconds();
        $this->refreshWith($first)->assertUnauthorized();

        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
        $this->refreshWith($second)->assertUnauthorized();
    }

    public function test_a_token_revoked_by_logout_is_not_graced(): void
    {
        $first = $this->login(User::factory()->create());

        $this->withCredentials()->withUnencryptedCookie('refresh_token', $first)->postJson('/api/auth/logout')->assertOk();

        $this->refreshWith($first)->assertUnauthorized();
        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
    }

    public function test_a_rotated_token_is_not_graced_once_its_successor_was_logged_out(): void
    {
        $first = $this->login(User::factory()->create());
        $second = $this->cookieValue($this->refreshWith($first)->assertOk());

        $this->withCredentials()->withUnencryptedCookie('refresh_token', $second)->postJson('/api/auth/logout')->assertOk();

        $this->refreshWith($first)->assertUnauthorized();
        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
    }

    public function test_a_rotated_token_is_not_graced_after_sign_out_everywhere(): void
    {
        $user = User::factory()->create();
        $first = $this->login($user);
        $this->refreshWith($first)->assertOk();

        app(AuthServiceInterface::class)->revokeSessions($user);

        $this->refreshWith($first)->assertUnauthorized();
        $this->assertSame(0, RefreshToken::whereNull('revoked_at')->count());
    }

    public function test_a_rotated_token_keeps_the_family_start_and_never_outlives_the_absolute_lifetime(): void
    {
        $token = $this->login(User::factory()->create());
        $startedAt = $this->row($token)->family_started_at;
        $this->assertTrue($startedAt->eq(now()));
        $this->assertTrue($this->row($token)->expires_at->eq(now()->addDays(30)));

        foreach ([29, 29, 29] as $days) {
            $this->travel($days)->days();
            $token = $this->cookieValue($this->refreshWith($token)->assertOk());
        }

        // Day 87: the sliding 30 days would end on day 117; the family ends on day 90.
        $row = $this->row($token);
        $this->assertTrue($row->family_started_at->eq($startedAt));
        $this->assertTrue($row->expires_at->eq($startedAt->copy()->addDays(90)));

        $this->travel(3)->days();
        $this->travel(1)->seconds();
        $this->refreshWith($token)->assertUnauthorized();
    }

    public function test_a_family_past_its_absolute_lifetime_is_revoked_even_when_the_token_has_not_expired(): void
    {
        $user = User::factory()->create();
        $plain = $this->storedToken(['user_id' => $user->id, 'family_id' => 'old', 'family_started_at' => now()->subDays(91), 'expires_at' => now()->addDays(10)]);
        $sibling = RefreshToken::factory()->create(['user_id' => $user->id, 'family_id' => 'old', 'family_started_at' => now()->subDays(91)]);

        $this->refreshWith($plain)->assertUnauthorized();

        $this->assertNotNull($this->row($plain)->revoked_at);
        $this->assertNotNull($sibling->fresh()->revoked_at);
    }

    public function test_an_inactive_user_cannot_refresh_and_the_family_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $this->login($user);
        $user->forceFill(['is_active' => false])->save();

        $this->refreshWith($token)->assertUnauthorized();

        $this->assertNotNull($this->row($token)->revoked_at);
        $this->assertNull($this->row($token)->replaced_by);
    }

    public function test_an_expired_refresh_token_revokes_its_family(): void
    {
        $user = User::factory()->create();
        $plain = $this->storedToken(['user_id' => $user->id, 'family_id' => 'fam', 'expires_at' => now()->subMinute()]);
        $sibling = RefreshToken::factory()->create(['user_id' => $user->id, 'family_id' => 'fam']);
        $otherFamily = RefreshToken::factory()->create(['user_id' => $user->id]);

        $this->refreshWith($plain)->assertUnauthorized();

        $this->assertNotNull($sibling->fresh()->revoked_at);
        $this->assertNull($otherFamily->fresh()->revoked_at);
    }
}
