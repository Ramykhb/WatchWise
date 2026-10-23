<?php

namespace App\Auth\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Auth\Exceptions\InvalidCredentials;
use App\Auth\Exceptions\InvalidRefreshToken;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Auth\Requests\LoginRequest;
use App\Auth\Requests\RegisterRequest;
use App\Auth\Support\AuthCookies;
use App\Auth\Support\TokenPair;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $auth,
        private readonly AuthCookies $cookies,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $pair = $this->auth->register($request->validated(), $request);

        return $this->cookies->attach($this->tokenResponse($pair, 201), $pair);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // TODO: add a captcha here (required after repeated failed attempts) so the per-email rate limit
        // in AppServiceProvider no longer lets a third party lock out an account owner. Do the same for
        // register and forgot-password.
        try {
            $pair = $this->auth->login($request->validated('email'), $request->validated('password'), $request);
        } catch (InvalidCredentials) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return $this->cookies->attach($this->tokenResponse($pair), $pair);
    }

    public function refresh(Request $request): JsonResponse
    {
        $plain = $request->cookie(config('jwt.refresh_cookie'));

        if (! is_string($plain) || $plain === '') {
            return $this->unauthenticated();
        }

        try {
            $pair = $this->auth->refresh($plain, $request);
        } catch (InvalidRefreshToken) {
            return $this->unauthenticated();
        }

        return $this->cookies->attach($this->tokenResponse($pair), $pair);
    }

    public function logout(Request $request): JsonResponse
    {
        $plain = $request->cookie(config('jwt.refresh_cookie'));

        if (is_string($plain) && $plain !== '') {
            $this->auth->logout($plain);
        }

        return $this->cookies->clear(response()->json(['message' => 'Logged out.']));
    }

    public function me(CurrentUser $current): JsonResponse
    {
        return response()->json(['user' => $current->user()]);
    }

    private function unauthenticated(): JsonResponse
    {
        return $this->cookies->clear(response()->json(['message' => 'Unauthenticated.'], 401));
    }

    private function tokenResponse(TokenPair $pair, int $status = 200): JsonResponse
    {
        return response()->json([
            'user' => $pair->user,
            'expires_at' => $pair->accessExpiresAt->format(DATE_ATOM),
        ], $status);
    }
}
