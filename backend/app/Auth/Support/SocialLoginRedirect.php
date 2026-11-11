<?php

namespace App\Auth\Support;

use Illuminate\Http\RedirectResponse;

/**
 * Where a social sign-in sends the browser back to: FRONTEND_URL/auth/callback with `status=success`, or with
 * `status=error&reason=<reason>`. Every failure also expires the state cookie, so a state is never accepted twice.
 * Used by `SocialAuthController` and by the social rate limiters, whose refusal must not be a raw JSON 429.
 */
final class SocialLoginRedirect
{
    public static function success(): RedirectResponse
    {
        return redirect(self::frontend('/auth/callback?status=success'))->withCookie(OAuthState::forget());
    }

    public static function failure(string $reason): RedirectResponse
    {
        return redirect(self::frontend('/auth/callback?status=error&reason='.$reason))->withCookie(OAuthState::forget());
    }

    private static function frontend(string $path): string
    {
        return rtrim((string) config('services.frontend_url'), '/').$path;
    }
}
