<?php

namespace App\Auth\Support;

use App\Auth\DTOs\Responses\AuthResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

class AuthCookies
{
    /**
     * A remembered login gets cookies that expire with their tokens. Without "Remember me" both are session cookies
     * (no Expires/Max-Age), so the browser drops them when it closes; the tokens still expire on the server.
     */
    public function attach(JsonResponse $response, AuthResponse $pair): JsonResponse
    {
        return $response
            ->withCookie($this->make(
                config('jwt.access_cookie'),
                $pair->accessToken,
                $pair->remember ? (int) config('jwt.access_ttl') : 0,
                '/',
            ))
            ->withCookie($this->make(
                config('jwt.refresh_cookie'),
                $pair->refreshToken,
                $pair->remember ? $this->minutesUntil($pair->refreshExpiresAt) : 0,
                config('jwt.refresh_cookie_path'),
            ));
    }

    public function clear(JsonResponse $response): JsonResponse
    {
        return $response
            ->withCookie(cookie()->forget(config('jwt.access_cookie'), '/'))
            ->withCookie(cookie()->forget(config('jwt.refresh_cookie'), config('jwt.refresh_cookie_path')));
    }

    /** The refresh cookie lives exactly as long as its token, which the family's absolute lifetime may shorten. */
    private function minutesUntil(CarbonInterface $expiresAt): int
    {
        return max(1, (int) ceil(now()->diffInSeconds($expiresAt) / 60));
    }

    /** `$minutes` 0 makes a session cookie. */
    private function make(string $name, string $value, int $minutes, string $path): Cookie
    {
        return cookie($name, $value, $minutes, $path, null, ! app()->environment('local', 'testing'), true, false, 'lax');
    }
}
