<?php

namespace App\Auth\InterfaceServices;

use App\Auth\DTOs\Requests\LoginData;
use App\Auth\DTOs\Requests\RegisterData;
use App\Auth\DTOs\Responses\AuthResponse;
use App\Auth\Exceptions\InvalidCredentials;
use App\Auth\Exceptions\InvalidRefreshToken;
use App\Auth\Exceptions\RegistrationClosed;
use App\Models\User;
use Illuminate\Http\Request;

interface AuthServiceInterface
{
    /**
     * @throws RegistrationClosed when an admin closed registration
     */
    public function register(RegisterData $data, Request $request): AuthResponse;

    /** @throws InvalidCredentials */
    public function login(LoginData $data, Request $request): AuthResponse;

    /** @throws InvalidRefreshToken */
    public function refresh(string $plainRefreshToken, Request $request): AuthResponse;

    public function logout(string $plainRefreshToken): void;

    /** Signs the user out everywhere by revoking every refresh token they hold. */
    public function revokeSessions(User $user): void;

    /** How many sessions the user has open: one per device whose refresh token is neither revoked nor expired. */
    public function activeSessionCount(User $user): int;

    /**
     * Signs the user out everywhere, then starts one fresh session for the caller so the device that
     * triggered the change stays signed in. The controller turns the result into the new cookies.
     */
    public function replaceSessions(User $user, Request $request): AuthResponse;
}
