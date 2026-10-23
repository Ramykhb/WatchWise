<?php

namespace App\Auth\Services;

use App\Auth\Exceptions\InvalidCredentials;
use App\Auth\Factories\TokenFactory;
use App\Auth\InterfaceRepository\UserRepositoryInterface;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Auth\Support\TokenPair;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TokenFactory $tokens,
    ) {}

    public function register(array $data, Request $request): TokenPair
    {
        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ])->refresh();

        event(new Registered($user));

        return $this->tokens->issue($user, $request);
    }

    public function login(string $email, string $password, Request $request): TokenPair
    {
        $user = $this->users->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password) || ! $user->is_active) {
            throw new InvalidCredentials;
        }

        return $this->tokens->issue($user, $request);
    }

    public function refresh(string $plainRefreshToken, Request $request): TokenPair
    {
        return $this->tokens->rotate($plainRefreshToken, $request);
    }

    public function logout(string $plainRefreshToken): void
    {
        $this->tokens->revoke($plainRefreshToken);
    }
}
