<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A member's display name is valid UTF-8 without the characters that could fake extra lines or reorder the text
 * around it where names are printed (emails, reviews, messages). Used by registration (Auth) and the profile form
 * (User). Joiners and variation selectors stay allowed, since emoji sequences ("👨‍👩‍👧") and several scripts need them.
 */
final class DisplayName implements ValidationRule
{
    /**
     * Control characters (Cc: line breaks, tabs, NUL...), unassigned (Cn), surrogate (Cs) and private-use (Co) code
     * points, the line and paragraph separators, the bidirectional embeddings, overrides and isolates
     * (U+202A-U+202E, U+2066-U+2069), the direction marks (U+200E, U+200F, U+061C) and the byte order mark. Other
     * format characters (Cf), such as the zero-width joiner U+200D and non-joiner U+200C, are allowed.
     */
    public const DISALLOWED = '[\p{Cc}\p{Cn}\p{Cs}\p{Co}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200E}\x{200F}\x{061C}\x{FEFF}]';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (! mb_check_encoding($value, 'UTF-8') || preg_match('/'.self::DISALLOWED.'/u', $value) === 1) {
            $fail('The :attribute may not contain line breaks, tabs or invisible direction characters.');
        }
    }
}
