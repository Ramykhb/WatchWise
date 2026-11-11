<?php

return [

    // HS256 signing secret for access tokens (min 32 chars). Set JWT_SECRET in .env.
    'secret' => env('JWT_SECRET'),

    // Written as `iss` and required to match on every access token.
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),

    // Written as `aud` and required to match on every access token. Defaults to the host of APP_URL.
    'audience' => env('JWT_AUDIENCE', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),

    // When false, an access token without an `iss` or `aud` claim (issued before they were checked) is still
    // accepted; a claim that is present must always match. Only meant for the 15 minutes after the deploy.
    'require_iss_aud' => (bool) env('JWT_REQUIRE_ISS_AUD', true),

    // Access token lifetime in minutes.
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 15),

    // Refresh token lifetime in days (sliding: each rotation starts a new window, up to refresh_family_max_days).
    'refresh_ttl_days' => (int) env('JWT_REFRESH_TTL_DAYS', 30),

    // Absolute lifetime of a login (refresh family) in days, however often it is refreshed.
    'refresh_family_max_days' => (int) env('JWT_REFRESH_FAMILY_MAX_DAYS', 90),

    // A login without "Remember me": its refresh token lasts this many hours (sliding: each rotation starts a new
    // window), up to refresh_session_family_max_days, and both cookies are session cookies (gone when the browser closes).
    'refresh_session_hours' => (int) env('JWT_REFRESH_SESSION_HOURS', 24),

    // Absolute lifetime in days of a login without "Remember me", however often it is refreshed.
    'refresh_session_family_max_days' => (int) env('JWT_REFRESH_SESSION_FAMILY_MAX_DAYS', 7),

    // A token rotated less than this many seconds ago may be presented again ONCE, by the same user agent (two
    // tabs, a retried request), and gets a sibling token instead of revoking the family, as long as the token
    // that replaced it is still valid. Any other reuse revokes the family.
    'refresh_reuse_grace_seconds' => (int) env('JWT_REFRESH_REUSE_GRACE_SECONDS', 20),

    // auth:prune-refresh-tokens deletes rows expired, or revoked other than by rotation, more than this many days
    // ago. Rotated tokens are kept until they expired that long ago, so their reuse is still detected.
    'refresh_prune_retention_days' => (int) env('JWT_REFRESH_PRUNE_RETENTION_DAYS', 7),

    'access_cookie' => 'access_token',
    'refresh_cookie' => 'refresh_token',

    // The refresh cookie is only sent to this path prefix (refresh + logout).
    'refresh_cookie_path' => '/api/auth',
];
