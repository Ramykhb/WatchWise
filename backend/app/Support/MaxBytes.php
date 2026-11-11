<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value is at most `$max` bytes long. Laravel's `max` counts characters, but bcrypt only reads the first
 * 72 bytes of a password, and a character outside ASCII takes two to four of them.
 */
final class MaxBytes implements ValidationRule
{
    public function __construct(private readonly int $max) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > $this->max) {
            $fail("The :attribute must not be longer than {$this->max} bytes (accented, non-Latin and emoji characters count as more than one).");
        }
    }
}
