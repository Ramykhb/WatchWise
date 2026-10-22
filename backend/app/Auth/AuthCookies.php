<?php

namespace App\Auth;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

class AuthCookies
{
    public function attach(JsonResponse $response, TokenPair $pair): JsonResponse
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
                (int) config('jwt.refresh_ttl_days') * 1440,
                config('jwt.refresh_cookie_path'),
            ));
    }

    public function clear(JsonResponse $response): JsonResponse
    {
        return $response
            ->withCookie(cookie()->forget(config('jwt.access_cookie'), '/'))
            ->withCookie(cookie()->forget(config('jwt.refresh_cookie'), config('jwt.refresh_cookie_path')));
    }

    private function make(string $name, string $value, int $minutes, string $path): Cookie
    {
        return cookie($name, $value, $minutes, $path, null, ! app()->environment('local', 'testing'), true, false, 'lax');
    }
}
