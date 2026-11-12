<?php

namespace App\Rating\Support;

/** The star values a rating can take. */
final class Stars
{
    public const MIN = 1;

    public const MAX = 5;

    /**
     * Every star value, lowest first.
     *
     * @return list<int>
     */
    public static function all(): array
    {
        return range(self::MIN, self::MAX);
    }
}
