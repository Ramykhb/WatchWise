<?php

namespace App\Auth\Support;

use App\Auth\DTOs\Responses\AuthResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

class AuthCookies
{
    public function attach(JsonResponse $response, AuthResponse $pair): JsonResponse
    {
        return $response
            ->withCookie($this->make(
                config('jwt.access_cookie'),
                $pair->accessToken,
                (int) config('jwt.access_ttl'),
                '/',
            ))
            ->withCookie($this->make(
                config('jwt.refresh_cookie'),
                $pair->refreshToken,
                $this->minutesUntil($pair->refreshExpiresAt),
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

    private function make(string $name, string $value, int $minutes, string $path): Cookie
    {
        return cookie($name, $value, $minutes, $path, null, ! app()->environment('local', 'testing'), true, false, 'lax');
    }
}
