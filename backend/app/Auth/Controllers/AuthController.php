<?php

namespace App\Auth\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Auth\DTOs\Responses\UserResponse;
use App\Auth\Exceptions\InvalidCredentials;
use App\Auth\Exceptions\InvalidRefreshToken;
use App\Auth\FormRequests\LoginRequest;
use App\Auth\FormRequests\RegisterRequest;
use App\Auth\InterfaceServices\AuthServiceInterface;
use App\Auth\Support\AuthCookies;
use App\Support\Controller;
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
        $auth = $this->auth->register($request->toDto(), $request);

        return $this->cookies->attach(response()->json($auth->body(), 201), $auth);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // TODO: add a captcha here (required after repeated failed attempts) so the per-email rate limit
        // in AppServiceProvider no longer lets a third party lock out an account owner. Do the same for
        // register and forgot-password.
        try {
            $auth = $this->auth->login($request->toDto(), $request);
        } catch (InvalidCredentials) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return $this->cookies->attach(response()->json($auth->body()), $auth);
    }

    public function refresh(Request $request): JsonResponse
    {
        $plain = $request->cookie(config('jwt.refresh_cookie'));

        if (! is_string($plain) || $plain === '') {
            return $this->unauthenticated();
        }

        try {
            $auth = $this->auth->refresh($plain, $request);
        } catch (InvalidRefreshToken) {
            return $this->unauthenticated();
        }

        return $this->cookies->attach(response()->json($auth->body()), $auth);
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
        return response()->json(['user' => UserResponse::fromModel($current->user())]);
    }

    private function unauthenticated(): JsonResponse
    {
        return $this->cookies->clear(response()->json(['message' => 'Unauthenticated.'], 401));
    }
}
