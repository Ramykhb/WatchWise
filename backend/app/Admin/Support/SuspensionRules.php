<?php

namespace App\Admin\Support;

/** The validation rules of the two optional details of a suspension, shared by the single and the bulk suspend requests. */
final class SuspensionRules
{
    /** How far ahead a suspension may end. */
    public const MAX_DAYS = 365;

    /** The longest reason, which the member reads in the suspension email. */
    public const REASON_MAX = 255;

    /**
     * @return list<string>
     */
    public static function reason(): array
    {
        return ['nullable', 'string', 'max:'.self::REASON_MAX];
    }

    /**
     * A moment in the future, at most a year ahead.
     *
     * @return list<string>
     */
    public static function until(): array
    {
        return ['nullable', 'date', 'after:now', 'before_or_equal:'.now()->addDays(self::MAX_DAYS)->toIso8601String()];
    }
}
