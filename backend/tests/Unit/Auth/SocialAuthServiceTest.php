<?php

namespace Tests\Unit\Auth;

use App\Auth\DTOs\Requests\SocialLoginData;
use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\Exceptions\AccountDisabled;
use App\Auth\Exceptions\UnverifiedProviderEmail;
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceRepository\RefreshTokenRepositoryInterface;
use App\Auth\InterfaceRepository\SocialAccountRepositoryInterface;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\Services\SocialAuthService;
use App\Auth\Support\DiscordOAuthClient;
use App\Auth\Support\TokenPair;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SocialAuthServiceTest extends TestCase
{
    private MockInterface $users;

    private MockInterface $accounts;

    private MockInterface $refreshTokens;

    private MockInterface $tokens;

    private SocialAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = Mockery::mock(UserRepositoryInterface::class);
        $this->accounts = Mockery::mock(SocialAccountRepositoryInterface::class);
        $this->refreshTokens = Mockery::mock(RefreshTokenRepositoryInterface::class);
        $this->tokens = Mockery::mock(TokenFactory::class);
        $this->service = new SocialAuthService($this->users, $this->accounts, $this->refreshTokens, $this->tokens, new DiscordOAuthClient);
    }

    private function socialUser(bool $emailVerified = true): SocialLoginData
    {
        return new SocialLoginData('google', 'pid-1', 'ada@example.com', 'Ada', 'ada', 'http://a/b.png', 'tok', $emailVerified);
    }

    private function pair(User $user): TokenPair
    {
        $user->name ??= 'Ada';
        $user->email ??= 'ada@example.com';
        $user->role ??= 'user';
        $user->profile_visibility ??= 'public';

        return new TokenPair($user, 'a', now(), 'r', now());
    }

    public function test_creates_a_new_verified_user(): void
    {
        $user = new User(['name' => 'Ada']);
        $user->id = 1;
        $user->is_active = true;
        $request = Request::create('/');

        $this->accounts->shouldReceive('findByProvider')->with('google', 'pid-1')->andReturnNull();
        $this->users->shouldReceive('findByEmail')->with('ada@example.com')->andReturnNull();
        $this->users->shouldReceive('create')->once()->withArgs(fn ($a) => $a['email'] === 'ada@example.com'
            && $a['name'] === 'Ada' && strlen($a['password']) === 40 && $a['avatar'] === 'http://a/b.png')->andReturn($user);
        $this->users->shouldReceive('markEmailVerified')->once()->with($user);
        $this->accounts->shouldReceive('create')->once()->with(['user_id' => 1, 'provider' => 'google', 'provider_id' => 'pid-1', 'access_token' => 'tok']);
        $this->tokens->shouldReceive('issue')->once()->with($user, $request)->andReturn($this->pair($user));

        $this->assertInstanceOf(AuthResponse::class, $this->service->loginWithProvider($this->socialUser(), $request));
    }

    public function test_links_a_verified_local_user_without_touching_credentials(): void
    {
        $user = new User;
        $user->id = 2;
        $user->is_active = true;
        $user->email_verified_at = now();

        $this->accounts->shouldReceive('findByProvider')->andReturnNull();
        $this->users->shouldReceive('findByEmail')->andReturn($user);
        $this->users->shouldNotReceive('updatePassword');
        $this->users->shouldNotReceive('create');
        $this->refreshTokens->shouldNotReceive('revokeAllForUser');
        $this->accounts->shouldReceive('create')->once();
        $this->tokens->shouldReceive('issue')->once()->andReturn($this->pair($user));

        $this->service->loginWithProvider($this->socialUser(), Request::create('/'));
    }

    public function test_takeover_of_unverified_local_account_resets_password_and_revokes_tokens(): void
    {
        $user = new User;
        $user->id = 3;
        $user->is_active = true;

        $this->accounts->shouldReceive('findByProvider')->andReturnNull();
        $this->users->shouldReceive('findByEmail')->andReturn($user);
        $this->users->shouldReceive('markEmailVerified')->once()->with($user);
        $this->users->shouldReceive('updatePassword')->once()->withArgs(fn ($u, $p) => $u === $user && strlen($p) === 40);
        $this->refreshTokens->shouldReceive('revokeAllForUser')->once()->with(3);
        $this->accounts->shouldReceive('create')->once();
        $this->tokens->shouldReceive('issue')->once()->andReturn($this->pair($user));

        $this->service->loginWithProvider($this->socialUser(), Request::create('/'));
    }

    public function test_rejects_unverified_provider_email(): void
    {
        $this->accounts->shouldReceive('findByProvider')->andReturnNull();
        $this->users->shouldNotReceive('findByEmail');
        $this->users->shouldNotReceive('create');
        $this->tokens->shouldNotReceive('issue');

        $this->expectException(UnverifiedProviderEmail::class);

        $this->service->loginWithProvider($this->socialUser(false), Request::create('/'));
    }

    public function test_rejects_disabled_account(): void
    {
        $user = new User;
        $user->id = 4;
        $user->is_active = false;
        $account = new SocialAccount;
        $account->setRelation('user', $user);

        $this->accounts->shouldReceive('findByProvider')->andReturn($account);
        $this->accounts->shouldReceive('updateAccessToken')->once()->with($account, 'tok');
        $this->tokens->shouldNotReceive('issue');

        $this->expectException(AccountDisabled::class);

        $this->service->loginWithProvider($this->socialUser(), Request::create('/'));
    }
}
