<?php

namespace App\Http\Controllers;

use App\Support\LikePattern;

abstract class Controller
{
    /**
     * A "contains" pattern for `LIKE ? ESCAPE '!'` (see LikePattern).
     */
    protected function containsPattern(string $text): string
    {
        return LikePattern::contains($text);
    }
}
