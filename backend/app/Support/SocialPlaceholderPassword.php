<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The shared password given to accounts created by Google, GitHub or Discord sign-in, so the app can
 * tell they never chose one. It comes from SOCIAL_PLACEHOLDER_PASSWORD and is never accepted at login.
 * When it is not configured, social accounts fall back to a random password and nothing is blocked.
 */
final class SocialPlaceholderPassword
{
    public static function value(): ?string
    {
        $value = config('services.social_password.placeholder');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The password a new social account is created with. The placeholder is hashed here because the
     * model's 'hashed' cast stores a value that already looks like a hash as-is, which would break
     * isStoredFor() if SOCIAL_PLACEHOLDER_PASSWORD were set to something like "$2y$...".
     */
    public static function forNewAccount(): string
    {
        $value = self::value();

        return $value !== null ? Hash::make($value) : Str::random(40);
    }

    /** Whether a typed password is the placeholder (constant-time, no hashing). */
    public static function matches(string $typed): bool
    {
        $value = self::value();

        return $value !== null && hash_equals($value, $typed);
    }

    /** Whether the user still has the placeholder, i.e. never set a password of their own. */
    public static function isStoredFor(User $user): bool
    {
        $value = self::value();

        return $value !== null && Hash::check($value, $user->password);
    }

    /** Validation rule so nobody can choose the placeholder as their own password. */
    public static function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && self::matches($value)) {
                $fail('Pick a different password.');
            }
        };
    }
}
