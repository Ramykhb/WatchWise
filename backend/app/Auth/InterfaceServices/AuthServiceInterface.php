<?php

namespace App\Auth\InterfaceServices;

use App\Auth\Exceptions\InvalidCredentials;
use App\Auth\Exceptions\InvalidRefreshToken;
use App\Auth\Support\TokenPair;
use Illuminate\Http\Request;

interface AuthServiceInterface
{
    /** @param  array{name: string, email: string, password: string}  $data */
    public function register(array $data, Request $request): TokenPair;

    /** @throws InvalidCredentials */
    public function login(string $email, string $password, Request $request): TokenPair;

    /** @throws InvalidRefreshToken */
    public function refresh(string $plainRefreshToken, Request $request): TokenPair;

    public function logout(string $plainRefreshToken): void;
}
