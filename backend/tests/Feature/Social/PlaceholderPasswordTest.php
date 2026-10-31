<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;

class PlaceholderPasswordTest extends SocialTestCase
{
    private const PLACEHOLDER = 'phpunit-placeholder-Xy9#only-for-tests';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.social_password.placeholder' => self::PLACEHOLDER, 'services.frontend_url' => 'http://front.test']);
    }

    private function socialSignUp(string $email): User
    {
        $socialUser = new SocialiteUser;
        $socialUser->map(['id' => 'gh1', 'name' => 'Soc User', 'nickname' => 'soc', 'email' => $email, 'avatar' => null]);
        $socialUser->token = 'provider-access-token';

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

        $this->withUnencryptedCookie('oauth_state', 'abc')
            ->get('/api/auth/social/github/callback?state=abc')
            ->assertRedirect('http://front.test/auth/callback?status=success');

        return User::where('email', $email)->firstOrFail();
    }

    public function test_a_social_sign_up_has_no_password_of_its_own(): void
    {
        $user = $this->socialSignUp('soc@example.com');

        $this->assertTrue(Hash::check(self::PLACEHOLDER, $user->password));
        $this->as($user)->getJson('/api/me')->assertOk()->assertJsonPath('has_password', false);
    }

    public function test_a_placeholder_that_looks_like_a_hash_still_works(): void
    {
        config(['services.social_password.placeholder' => Hash::make('made-up')]);
        $user = $this->socialSignUp('soc@example.com');

        $this->as($user)->getJson('/api/me')->assertJsonPath('has_password', false);
        $this->as($user)->putJson('/api/me/password', ['password' => 'mine12345', 'password_confirmation' => 'mine12345'])->assertOk();
        $this->as($user)->getJson('/api/me')->assertJsonPath('has_password', true);
    }

    public function test_the_placeholder_never_signs_anyone_in(): void
    {
        $user = $this->socialSignUp('soc@example.com');

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => self::PLACEHOLDER])->assertUnauthorized();
    }

    public function test_a_nul_byte_after_the_placeholder_does_not_sign_anyone_in(): void
    {
        $user = $this->socialSignUp('soc@example.com');

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => self::PLACEHOLDER."\0abc"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_an_account_on_the_placeholder_is_refused_whatever_is_typed(): void
    {
        // Even the exact stored password is refused: the account never chose it.
        $user = User::factory()->create(['password' => self::PLACEHOLDER]);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => self::PLACEHOLDER])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => self::PLACEHOLDER.' '])->assertUnauthorized();
    }

    public function test_a_new_password_cannot_contain_a_nul_byte(): void
    {
        $user = $this->socialSignUp('soc@example.com');

        $this->as($user)->putJson('/api/me/password', ['password' => "mine12345\0x", 'password_confirmation' => "mine12345\0x"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_a_social_user_sets_a_first_password_without_the_current_one(): void
    {
        $user = $this->socialSignUp('soc@example.com');

        $this->as($user)->putJson('/api/me/password', ['password' => 'mine12345', 'password_confirmation' => 'mine12345'])->assertOk();

        $this->as($user)->getJson('/api/me')->assertJsonPath('has_password', true);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'mine12345'])->assertOk();
        $this->as($user)->putJson('/api/me/password', ['password' => 'other12345', 'password_confirmation' => 'other12345'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'Enter your current password.');
    }

    public function test_a_user_with_a_password_still_has_to_give_it(): void
    {
        $user = User::factory()->create();

        $this->as($user)->getJson('/api/me')->assertJsonPath('has_password', true);
        $this->as($user)->putJson('/api/me/password', ['password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_nobody_can_choose_the_placeholder_as_their_password(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Jane', 'email' => 'jane@example.com',
            'password' => self::PLACEHOLDER, 'password_confirmation' => self::PLACEHOLDER,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $user = $this->socialSignUp('soc@example.com');
        $this->as($user)->putJson('/api/me/password', ['password' => self::PLACEHOLDER, 'password_confirmation' => self::PLACEHOLDER])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
