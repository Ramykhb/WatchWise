<?php

namespace App\Auth\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * A password check against a hash nobody knows, for the sign-in paths that have no hash of their own (unknown
 * email, account without a password). They then take as long as a wrong password, so the response time does not
 * tell whether an account exists. The hash is made once per cost setting and cached: making it on every request
 * would double the time again. When the cache store is unavailable, the hash is made in-process instead and kept
 * for the rest of the process, so sign-in never fails because of the cache.
 */
class DummyPassword
{
    /** @var array<int, string> hashes made while the cache was unavailable, by bcrypt cost */
    private static array $fallbackHashes = [];

    public function check(string $password): void
    {
        Hash::check($password, $this->hash());
    }

    private function hash(): string
    {
        $rounds = (int) config('hashing.bcrypt.rounds', 12);

        try {
            return (string) Cache::rememberForever('auth:dummy-password-hash:'.$rounds, fn (): string => self::make());
        } catch (Throwable) {
            return self::$fallbackHashes[$rounds] ??= self::make();
        }
    }

    private static function make(): string
    {
        return Hash::make(Str::random(40));
    }
}
