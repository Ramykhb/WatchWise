<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * No other account uses this address in any letter case. Compares `LOWER(email)`, so a row stored before
 * emails were lowercased ("Ali@Example.com") still blocks "ali@example.com".
 */
final class UniqueEmail implements ValidationRule
{
    public function __construct(private readonly ?int $exceptUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $taken = User::query()
            ->whereRaw('LOWER(email) = ?', [EmailAddress::normalize($value)])
            ->when($this->exceptUserId !== null, fn ($query) => $query->whereKeyNot($this->exceptUserId))
            ->exists();

        if ($taken) {
            $fail('validation.unique')->translate();
        }
    }
}
