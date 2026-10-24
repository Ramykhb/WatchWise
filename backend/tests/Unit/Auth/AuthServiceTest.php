<?php

namespace Tests\Unit\Auth;

use App\Auth\DTOs\Requests\LoginData;
use App\Auth\DTOs\Requests\RegisterData;
use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\Exceptions\InvalidCredentials;
use App\Auth\Exceptions\InvalidRefreshToken;
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\Services\AuthService;
use App\Auth\Support\TokenPair;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private function fakeUser(array $attrs = []): User
    {
        $user = new User;
        $user->forceFill(array_merge([
            'id' => 1,
            'name' => 'A',
            'role' => 'user',
            'profile_visibility' => 'public',
            'email' => 'a@example.com',
            'password' => Hash::make('secret-pass'),
            'is_active' => true,
        ], $attrs));

        return $user;
    }

    private function fakePair(User $user): TokenPair
    {
        return new TokenPair($user, 'a', now(), 'r', now());
    }

    private function users(): MockInterface
    {
        return Mockery::mock(UserRepositoryInterface::class);
    }

    private function tokens(): MockInterface
    {
        return Mockery::mock(TokenFactory::class);
    }

    public function test_login_rejects_unknown_email(): void
    {
        $users = $this->users();
        $users->shouldReceive('findByEmail')->with('a@example.com')->andReturnNull();

        $this->expectException(InvalidCredentials::class);

        (new AuthService($users, $this->tokens()))->login(new LoginData('a@example.com', 'secret-pass'), Request::create('/'));
    }

    public function test_login_rejects_wrong_password(): void
    {
        $users = $this->users();
        $users->shouldReceive('findByEmail')->andReturn($this->fakeUser());

        $this->expectException(InvalidCredentials::class);

        (new AuthService($users, $this->tokens()))->login(new LoginData('a@example.com', 'nope'), Request::create('/'));
    }

    public function test_login_rejects_inactive_user(): void
    {
        $users = $this->users();
        $users->shouldReceive('findByEmail')->andReturn($this->fakeUser(['is_active' => false]));

        $this->expectException(InvalidCredentials::class);

        (new AuthService($users, $this->tokens()))->login(new LoginData('a@example.com', 'secret-pass'), Request::create('/'));
    }

    public function test_login_issues_tokens(): void
    {
        $user = $this->fakeUser();
        $request = Request::create('/');
        $pair = $this->fakePair($user);
        $users = $this->users();
        $users->shouldReceive('findByEmail')->andReturn($user);
        $tokens = $this->tokens();
        $tokens->shouldReceive('issue')->once()->with($user, $request)->andReturn($pair);

        $result = (new AuthService($users, $tokens))->login(new LoginData('a@example.com', 'secret-pass'), $request);

        $this->assertInstanceOf(AuthResponse::class, $result);
        $this->assertSame('a', $result->accessToken);
        $this->assertSame('r', $result->refreshToken);
        $this->assertSame('a@example.com', $result->user->email);
    }

    public function test_register_fires_registered_and_issues_tokens(): void
    {
        Event::fake([Registered::class]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('refresh')->once()->andReturnSelf();
        $request = Request::create('/');
        $pair = $this->fakePair($this->fakeUser());
        $users = $this->users();
        $users->shouldReceive('create')->once()
            ->with(['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret-pass'])
            ->andReturn($user);
        $tokens = $this->tokens();
        $tokens->shouldReceive('issue')->once()->with($user, $request)->andReturn($pair);

        $result = (new AuthService($users, $tokens))->register(
            new RegisterData('A', 'a@example.com', 'secret-pass'),
            $request,
        );

        $this->assertSame('a', $result->accessToken);
        Event::assertDispatched(Registered::class);
    }

    public function test_refresh_delegates_and_propagates_invalid_token(): void
    {
        $request = Request::create('/');
        $pair = $this->fakePair($this->fakeUser());
        $tokens = $this->tokens();
        $tokens->shouldReceive('rotate')->with('good', $request)->andReturn($pair);
        $tokens->shouldReceive('rotate')->with('bad', $request)->andThrow(new InvalidRefreshToken);
        $service = new AuthService($this->users(), $tokens);

        $this->assertSame('r', $service->refresh('good', $request)->refreshToken);

        $this->expectException(InvalidRefreshToken::class);
        $service->refresh('bad', $request);
    }

    public function test_logout_delegates_to_revoke(): void
    {
        $tokens = $this->tokens();
        $tokens->shouldReceive('revoke')->once()->with('tok');

        (new AuthService($this->users(), $tokens))->logout('tok');

        $this->addToAssertionCount(1);
    }
}
