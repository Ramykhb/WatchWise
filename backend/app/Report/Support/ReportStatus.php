<?php

namespace App\Report\Support;

use InvalidArgumentException;

/** Where a report stands. Stored as the string value; never rename one, rows keep it. */
enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    /** The status a staff decision closes a report with: `resolved` or `dismissed`, never `open`. */
    public static function closing(string $status): self
    {
        $decision = self::from($status);

        if ($decision === self::Open) {
            throw new InvalidArgumentException('A report is closed as resolved or dismissed, not open.');
        }

        return $decision;
    }
}
