<?php

/*
 * Read on every request by Laravel's TrustProxies middleware (`config('trustedproxy.proxies')`); the trusted
 * headers are set in bootstrap/app.php. Without trusted proxies `$request->ip()` is the proxy in front of the
 * app, so every IP-keyed rate limit would be one bucket shared by all visitors.
 *
 * TRUSTED_PROXIES: comma-separated IPs/CIDRs, or `*` for "the peer that connected to us". Empty means none.
 * Defaults to `*` when APP_ENV=production and to none otherwise.
 */
$proxies = env('TRUSTED_PROXIES', env('APP_ENV') === 'production' ? '*' : null);

return [
    'proxies' => is_string($proxies) && trim($proxies) !== '' ? trim($proxies) : null,
];
