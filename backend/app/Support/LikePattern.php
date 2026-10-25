<?php

namespace App\Support;

final class LikePattern
{
    /**
     * A "contains" pattern for `LIKE ? ESCAPE '!'` where % and _ in the search text match
     * literally ("_" is common in handles). "!" is used because a backslash means different
     * things in MySQL and SQLite string literals.
     */
    public static function contains(string $text): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';
    }
}
