<?php

namespace App\Report\Support;

/** What a report is about. Stored as the string value; never rename one, rows keep it. */
enum ReportTargetType: string
{
    case Review = 'review';
    case Message = 'message';
    case MovieList = 'list';
    case Room = 'room';
    case Profile = 'profile';

    /**
     * The reasons a member may give for this kind of content. Review reasons keep the wording the review
     * report has always had; the others are lowercase.
     *
     * @return list<string>
     */
    public function reasons(): array
    {
        return match ($this) {
            self::Review => ['Spam', 'Links', 'Spoilers', 'Offensive language'],
            self::Message => ['spam', 'harassment', 'hate', 'spoilers', 'other'],
            self::MovieList, self::Room, self::Profile => ['spam', 'harassment', 'inappropriate', 'other'],
        };
    }

    /**
     * Every reason of every kind, each once; what the staff queue's reason filter accepts.
     *
     * @return list<string>
     */
    public static function allReasons(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (self $type): array => $type->reasons(), self::cases()))));
    }

    /** The name the member-facing confirmation uses ("Message reported."). */
    public function label(): string
    {
        return match ($this) {
            self::Review => 'Review',
            self::Message => 'Message',
            self::MovieList => 'List',
            self::Room => 'Room',
            self::Profile => 'Profile',
        };
    }

    /**
     * Whether reporting the same content again replaces the reason of the member's earlier report. Reviews have
     * always worked that way (a repeat answers 200 with the changed reason); every other kind keeps the first report as it was.
     */
    public function replacesReasonOnRepeat(): bool
    {
        return $this === self::Review;
    }
}
