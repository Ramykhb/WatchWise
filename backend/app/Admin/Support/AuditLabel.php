<?php

namespace App\Admin\Support;

use App\Models\Review;
use App\Models\User;

/** The names the audit log keeps for what staff acted on, so an entry stays readable after the record is gone. */
final class AuditLabel
{
    /** Usernames are what the log keeps as an account's label; emails never are. */
    public static function user(User $user): string
    {
        return $user->username ?? $user->name;
    }

    /** Needs the review's `user` loaded. */
    public static function review(Review $review): ?string
    {
        return $review->user === null ? null : 'Review by '.self::user($review->user);
    }
}
