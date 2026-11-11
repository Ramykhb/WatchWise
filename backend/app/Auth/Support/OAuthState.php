<?php

namespace App\Auth\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The OAuth `state` round trip of the social sign-in. The value is `<provider>:<random>`, kept in a short-lived
 * HttpOnly, SameSite=Lax cookie and echoed by the provider, so a state issued for one provider never passes at
 * another's callback.
 *
 * Outside local/testing the cookie is Secure and named `__Host-oauth_state`: browsers only accept that prefix on
 * a Secure cookie with Path=/ and no Domain, so a sibling subdomain cannot plant its own state cookie for a login
 * CSRF. The cookie is built directly (not with `cookie()`) so `SESSION_DOMAIN` can never add a Domain to it.
 */
final class OAuthState
{
    private const NAME = 'oauth_state';

    private const LIFETIME_MINUTES = 10;

    public static function issue(string $provider): string
    {
        return $provider.':'.Str::random(40);
    }

    public static function cookieName(): string
    {
        return self::secure() ? '__Host-'.self::NAME : self::NAME;
    }

    /** True when the echoed state equals the cookie and both were issued for `$provider`. */
    public static function matches(mixed $cookie, mixed $given, string $provider): bool
    {
        return is_string($cookie)
            && is_string($given)
            && str_starts_with($cookie, $provider.':')
            && hash_equals($cookie, $given);
    }

    public static function cookie(string $state): Cookie
    {
        return self::make($state, now()->addMinutes(self::LIFETIME_MINUTES)->getTimestamp());
    }

    /** Expires the cookie; sent on every callback outcome so a state can never be replayed. */
    public static function forget(): Cookie
    {
        return self::make('', 1);
    }

    private static function make(string $value, int $expires): Cookie
    {
        return Cookie::create(self::cookieName(), $value, $expires, '/', null, self::secure(), true, false, Cookie::SAMESITE_LAX);
    }

    private static function secure(): bool
    {
        return ! app()->environment('local', 'testing');
    }
}
