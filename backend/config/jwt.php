<?php

return [

    // HS256 signing secret for access tokens (min 32 chars). Set JWT_SECRET in .env.
    'secret' => env('JWT_SECRET'),

    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),

    // Access token lifetime in minutes.
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 15),

    // Refresh token lifetime in days.
    'refresh_ttl_days' => (int) env('JWT_REFRESH_TTL_DAYS', 30),

    'access_cookie' => 'access_token',
    'refresh_cookie' => 'refresh_token',

    // The refresh cookie is only sent to this path prefix (refresh + logout).
    'refresh_cookie_path' => '/api/auth',
];
