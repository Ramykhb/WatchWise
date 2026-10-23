<?php

namespace App\Http\Controllers\Auth;

use App\Auth\AuthCookies;
use App\Auth\Exceptions\InvalidRefreshToken;
use App\Auth\TokenFactory;
use App\Contracts\CurrentUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private readonly TokenFactory $tokens,
        private readonly AuthCookies $cookies,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ])->refresh();

        event(new Registered($user));

        return $this->respondWithTokens($user, $request, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // TODO: add a captcha here (required after repeated failed attempts) so the per-email rate limit
        // in AppServiceProvider no longer lets a third party lock out an account owner. Do the same for
        // register and forgot-password.
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password) || ! $user->is_active) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return $this->respondWithTokens($user, $request);
    }

    public function refresh(Request $request): JsonResponse
    {
        $plain = $request->cookie(config('jwt.refresh_cookie'));

        if (! is_string($plain) || $plain === '') {
            return $this->cookies->clear(response()->json(['message' => 'Unauthenticated.'], 401));
        }

        try {
            $pair = $this->tokens->rotate($plain, $request);
        } catch (InvalidRefreshToken) {
            return $this->cookies->clear(response()->json(['message' => 'Unauthenticated.'], 401));
        }

        return $this->cookies->attach($this->tokenResponse($pair->user, $pair->accessExpiresAt), $pair);
    }

    public function logout(Request $request): JsonResponse
    {
        $plain = $request->cookie(config('jwt.refresh_cookie'));

        if (is_string($plain) && $plain !== '') {
            $this->tokens->revoke($plain);
        }

        return $this->cookies->clear(response()->json(['message' => 'Logged out.']));
    }

    public function me(CurrentUser $current): JsonResponse
    {
        return response()->json(['user' => $current->user()]);
    }

    private function respondWithTokens(User $user, Request $request, int $status = 200): JsonResponse
    {
        $pair = $this->tokens->issue($user, $request);

        return $this->cookies->attach($this->tokenResponse($user, $pair->accessExpiresAt, $status), $pair);
    }

    private function tokenResponse(User $user, \DateTimeInterface $accessExpiresAt, int $status = 200): JsonResponse
    {
        return response()->json([
            'user' => $user,
            'expires_at' => $accessExpiresAt->format(DATE_ATOM),
        ], $status);
    }
}
