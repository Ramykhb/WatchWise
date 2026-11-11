<?php

namespace App\Auth\Support;

/**
 * The client address the auth rate limits count by. A single IPv6 host usually holds a whole /64 and can pick a new
 * address from it for every request, so an IPv6 address counts as its /64 prefix. IPv4 addresses, and IPv4-mapped
 * IPv6 addresses (`::ffff:203.0.113.5`), count as the IPv4 address. Anything else is returned unchanged.
 */
final class RateLimitAddress
{
    public static function of(?string $ip): string
    {
        $ip = (string) $ip;

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return $ip;
        }

        $packed = (string) inet_pton($ip);

        if (strlen($packed) === 4) {
            return (string) inet_ntop($packed);
        }

        if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
