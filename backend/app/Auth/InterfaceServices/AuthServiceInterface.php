<?php

namespace App\Auth\InterfaceServices;

use App\Auth\DTOs\Requests\LoginData;
use App\Auth\DTOs\Requests\RegisterData;
use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\Exceptions\InvalidCredentials;
use App\Auth\Exceptions\InvalidRefreshToken;
use App\Models\User;
use Illuminate\Http\Request;

interface AuthServiceInterface
{
    public function register(RegisterData $data, Request $request): AuthResponse;

    /** @throws InvalidCredentials */
    public function login(LoginData $data, Request $request): AuthResponse;

    /** @throws InvalidRefreshToken */
    public function refresh(string $plainRefreshToken, Request $request): AuthResponse;

    public function logout(string $plainRefreshToken): void;

    /** Signs the user out everywhere by revoking every refresh token they hold. */
    public function revokeSessions(User $user): void;
}
