<?php

namespace Tests\Unit\Auth;

use App\Auth\DTOs\Requests\SocialCallbackData;
use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\Exceptions\AccountDisabled;
use App\Auth\Exceptions\InvalidOAuthState;
use App\Auth\Exceptions\UnverifiedProviderEmail;
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\SocialAccountRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\DiscordOAuthClientInterface;
use App\Auth\InterfaceServices\GooglePhotoInterface;
use App\Auth\Services\SocialAuthService;
use App\Auth\Support\TokenPair;
use App\Models\SocialAccount;
use App\Models\User;
use App\Setting\InterfaceServices\SettingServiceInterface;
use App\User\InterfaceServices\EmailChangeServiceInterface;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SocialAuthServiceTest extends TestCase
{
    private MockInterface $users;

    private MockInterface $accounts;

    private MockInterface $refreshTokens;

    private MockInterface $tokens;

    private MockInterface $emailChanges;

    private SocialAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://api.test/cb']]);

        $this->users = Mockery::mock(UserRepositoryInterface::class);
        $this->accounts = Mockery::mock(SocialAccountRepositoryInterface::class);
        $this->refreshTokens = Mockery::mock(RefreshTokenRepositoryInterface::class);
        $this->tokens = Mockery::mock(TokenFactory::class);
        $this->emailChanges = Mockery::mock(EmailChangeServiceInterface::class);
        $googlePhoto = Mockery::mock(GooglePhotoInterface::class);
        $googlePhoto->shouldReceive('isPlaceholder')->andReturnNull();

        $this->service = new SocialAuthService(
            $this->users, $this->accounts, $this->refreshTokens, $this->tokens, Mockery::mock(DiscordOAuthClientInterface::class), $googlePhoto, $this->emailChanges,
            Mockery::mock(SettingServiceInterface::class, ['registrationOpen' => true]),
        );
    }

    private function googleReturns(bool $emailVerified = true): void
    {
        $socialUser = new SocialiteUser;
        $socialUser->map(['id' => 'pid-1', 'name' => 'Ada', 'nickname' => 'ada', 'email' => 'ada@example.com', 'avatar' => 'http://a/b.png']);
        $socialUser->token = 'tok';
        $socialUser->user = ['email_verified' => $emailVerified];

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    private function googleCallback(bool $stateMatches = true): SocialCallbackData
    {
        return new SocialCallbackData('google', $stateMatches, 'code', null, 'Browser/1.0', '203.0.113.9');
    }

    private function pair(User $user): TokenPair
    {
        $user->name ??= 'Ada';
        $user->email ??= 'ada@example.com';
        $user->role ??= 'user';
        $user->profile_visibility ??= 'public';

        return new TokenPair($user, 'a', now(), 'r', now());
    }

    public function test_creates_a_new_verified_user_without_storing_the_provider_token(): void
    {
        $this->googleReturns();
        $user = new User(['name' => 'Ada']);
        $user->id = 1;
        $user->is_active = true;

        $this->accounts->shouldReceive('findByProvider')->with('google', 'pid-1')->andReturnNull();
        $this->users->shouldReceive('findByEmail')->with('ada@example.com')->andReturnNull();
        $this->users->shouldReceive('create')->once()->withArgs(fn ($a) => $a['email'] === 'ada@example.com'
            && $a['name'] === 'Ada' && $a['password'] === null && $a['avatar'] === 'http://a/b.png')->andReturn($user);
        $this->users->shouldReceive('markEmailVerified')->once()->with($user);
        $this->accounts->shouldReceive('create')->once()->with(['user_id' => 1, 'provider' => 'google', 'provider_id' => 'pid-1']);
        $this->tokens->shouldReceive('issue')->once()
            ->withArgs(fn ($issuedTo, Request $client) => $issuedTo === $user && $client->ip() === '203.0.113.9' && $client->userAgent() === 'Browser/1.0')
            ->andReturn($this->pair($user));

        $this->assertInstanceOf(AuthResponse::class, $this->service->loginWithProvider($this->googleCallback()));
    }

    public function test_links_a_verified_local_user_without_touching_credentials(): void
    {
        $this->googleReturns();
        $user = new User;
        $user->id = 2;
        $user->is_active = true;
        $user->email_verified_at = now();

        $this->accounts->shouldReceive('findByProvider')->andReturnNull();
        $this->users->shouldReceive('findByEmail')->andReturn($user);
        $this->users->shouldNotReceive('clearPassword');
        $this->users->shouldNotReceive('create');
        $this->refreshTokens->shouldNotReceive('revokeAllForUser');
        $this->emailChanges->shouldNotReceive('discardPendingChange');
        $this->accounts->shouldReceive('create')->once();
        $this->tokens->shouldReceive('issue')->once()->andReturn($this->pair($user));

        $this->service->loginWithProvider($this->googleCallback());
    }

    public function test_takeover_of_unverified_local_account_clears_password_and_revokes_tokens(): void
    {
        $this->googleReturns();
        $user = new User;
        $user->id = 3;
        $user->is_active = true;

        $this->accounts->shouldReceive('findByProvider')->andReturnNull();
        $this->users->shouldReceive('findByEmail')->andReturn($user);
        $this->users->shouldReceive('markEmailVerified')->once()->with($user);
        $this->users->shouldReceive('clearPassword')->once()->with($user);
        $this->refreshTokens->shouldReceive('revokeAllForUser')->once()->with($user)->andReturn(1);
        $this->emailChanges->shouldReceive('discardPendingChange')->once()->with($user);
        $this->accounts->shouldReceive('create')->once();
        $this->tokens->shouldReceive('issue')->once()->andReturn($this->pair($user));

        $this->service->loginWithProvider($this->googleCallback());
    }

    public function test_rejects_unverified_provider_email(): void
    {
        $this->googleReturns(emailVerified: false);
        $this->accounts->shouldReceive('findByProvider')->andReturnNull();
        $this->users->shouldNotReceive('findByEmail');
        $this->users->shouldNotReceive('create');
        $this->tokens->shouldNotReceive('issue');

        $this->expectException(UnverifiedProviderEmail::class);

        $this->service->loginWithProvider($this->googleCallback());
    }

    public function test_rejects_a_disabled_linked_account_before_changing_anything(): void
    {
        $this->googleReturns();
        $user = new User;
        $user->id = 4;
        $user->is_active = false;
        $account = new SocialAccount;
        $account->setRelation('user', $user);

        $this->accounts->shouldReceive('findByProvider')->andReturn($account);
        $this->accounts->shouldNotReceive('create');
        $this->users->shouldNotReceive('clearAvatar');
        $this->tokens->shouldNotReceive('issue');

        $this->expectException(AccountDisabled::class);

        $this->service->loginWithProvider($this->googleCallback());
    }

    public function test_rejects_a_mismatched_state_before_asking_the_provider(): void
    {
        Socialite::shouldReceive('driver')->never();
        $this->accounts->shouldNotReceive('findByProvider');

        $this->expectException(InvalidOAuthState::class);

        $this->service->loginWithProvider($this->googleCallback(stateMatches: false));
    }

    public function test_never_trusts_the_email_of_a_provider_it_does_not_know(): void
    {
        config(['services.gitlab' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://api.test/cb']]);
        $socialUser = new SocialiteUser;
        $socialUser->map(['id' => 'gl-1', 'name' => 'Ada', 'nickname' => 'ada', 'email' => 'ada@example.com', 'avatar' => null]);
        $socialUser->user = ['email_verified' => true];
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with('gitlab')->andReturn($driver);

        $this->accounts->shouldReceive('findByProvider')->with('gitlab', 'gl-1')->andReturnNull();
        $this->users->shouldNotReceive('findByEmail');
        $this->users->shouldNotReceive('create');

        $this->expectException(UnverifiedProviderEmail::class);

        $this->service->loginWithProvider(new SocialCallbackData('gitlab', true, 'code', null, null, null));
    }
}
