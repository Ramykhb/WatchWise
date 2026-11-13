<?php

namespace Tests\Feature\Setting;

use App\Models\RefreshToken;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Auth\SignsInWithProviders;
use Tests\TestCase;

/** `registration_open=false` refuses every way of creating a new account and leaves existing members signing in. */
class RegistrationSettingTest extends TestCase
{
    use ChangesSettings;
    use RefreshDatabase;
    use SignsInWithProviders;

    private const SUCCESS = 'http://front.test/auth/callback?status=success';

    private const CLOSED = 'http://front.test/auth/callback?status=error&reason=registration_closed';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.frontend_url' => 'http://front.test']);
        $this->configureSocialProviders();
    }

    /**
     * @return array<string, string>
     */
    private function registration(string $email = 'new@example.com'): array
    {
        return ['name' => 'New Member', 'email' => $email, 'password' => 'secret1234', 'password_confirmation' => 'secret1234'];
    }

    public function test_registration_is_open_by_default(): void
    {
        $this->postJson('/api/auth/register', $this->registration())->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_a_closed_registration_refuses_a_password_sign_up_with_a_clear_403(): void
    {
        $this->changeSettings(['registration_open' => false]);
        Event::fake();

        $this->postJson('/api/auth/register', $this->registration())
            ->assertForbidden()
            ->assertExactJson(['message' => 'New accounts cannot be created right now.', 'code' => 'registration_closed'])
            ->assertCookieMissing('access_token')
            ->assertCookieMissing('refresh_token');

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        Event::assertNotDispatched(Registered::class);
    }

    public function test_existing_members_still_sign_in_with_a_password_while_registration_is_closed(): void
    {
        User::factory()->create(['email' => 'old@example.com', 'password' => 'secret1234']);
        $this->changeSettings(['registration_open' => false]);

        $this->postJson('/api/auth/login', ['email' => 'old@example.com', 'password' => 'secret1234'])->assertOk();
    }

    public function test_reopening_registration_lets_sign_ups_through_again(): void
    {
        $this->changeSettings(['registration_open' => false]);
        $this->postJson('/api/auth/register', $this->registration())->assertForbidden();

        $this->changeSettings(['registration_open' => true]);

        $this->postJson('/api/auth/register', $this->registration())->assertCreated();
    }

    public function test_a_new_social_account_is_refused_with_the_registration_closed_reason(): void
    {
        $this->changeSettings(['registration_open' => false]);
        $this->mockSocialite('github', $this->socialiteUser('gh-new', 'fresh@example.com'));

        $response = $this->hitSocialCallback('github');

        $response->assertRedirect(self::CLOSED);
        $this->assertTrue($response->getCookie('oauth_state', false)->isCleared());
        $this->assertNull($response->getCookie('access_token', false));
        $this->assertDatabaseMissing('users', ['email' => 'fresh@example.com']);
        $this->assertSame(0, SocialAccount::count());
        $this->assertSame(0, RefreshToken::count());
    }

    public function test_a_member_who_signed_up_with_a_provider_still_signs_in_while_registration_is_closed(): void
    {
        $member = User::factory()->create();
        SocialAccount::create(['user_id' => $member->id, 'provider' => 'github', 'provider_id' => 'gh1']);
        $this->changeSettings(['registration_open' => false]);
        $this->mockSocialite('github', $this->socialiteUser('gh1', null));

        $this->hitSocialCallback('github')->assertRedirect(self::SUCCESS);

        $this->assertSame(1, RefreshToken::where('user_id', $member->id)->count());
    }

    public function test_an_existing_member_signing_in_with_a_new_provider_by_email_is_not_a_new_account(): void
    {
        $member = User::factory()->create(['email' => 'old@example.com']);
        $this->changeSettings(['registration_open' => false]);
        $users = User::count();
        $this->mockSocialite('github', $this->socialiteUser('gh-link', 'old@example.com'));

        $this->hitSocialCallback('github')->assertRedirect(self::SUCCESS);

        $this->assertSame(1, SocialAccount::where('user_id', $member->id)->count());
        $this->assertSame($users, User::count());
    }

    public function test_an_open_registration_still_creates_a_social_account(): void
    {
        $this->mockSocialite('github', $this->socialiteUser('gh-new', 'fresh@example.com'));

        $this->hitSocialCallback('github')->assertRedirect(self::SUCCESS);

        $this->assertDatabaseHas('users', ['email' => 'fresh@example.com']);
    }
}
