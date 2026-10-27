<?php

namespace App\Auth\Services;

use App\Auth\DTOs\Requests\LoginData;
use App\Auth\DTOs\Requests\RegisterData;
use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\Exceptions\InvalidCredentials;
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TokenFactory $tokens,
    ) {}

    public function register(RegisterData $data, Request $request): AuthResponse
    {
        $user = $this->users->create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
        ])->refresh();

        event(new Registered($user));

        return AuthResponse::fromTokenPair($this->tokens->issue($user, $request));
    }

    public function login(LoginData $data, Request $request): AuthResponse
    {
        $user = $this->users->findByEmail($data->email);

        if (! $user || ! Hash::check($data->password, $user->password) || ! $user->is_active) {
            throw new InvalidCredentials;
        }

        return AuthResponse::fromTokenPair($this->tokens->issue($user, $request));
    }

    public function refresh(string $plainRefreshToken, Request $request): AuthResponse
    {
        return AuthResponse::fromTokenPair($this->tokens->rotate($plainRefreshToken, $request));
    }

    public function logout(string $plainRefreshToken): void
    {
        $this->tokens->revoke($plainRefreshToken);
    }

    public function revokeSessions(User $user): void
    {
        $this->tokens->revokeAllForUser($user);
    }
}
