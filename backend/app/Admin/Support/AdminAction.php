<?php

namespace App\Admin\Support;

/** Every staff action the audit log records. Stored as the string value; never rename one, rows keep it. */
enum AdminAction: string
{
    case UserRoleChanged = 'user.role_changed';
    case UserSuspended = 'user.suspended';
    case UserReinstated = 'user.reinstated';
    case UserDeleted = 'user.deleted';
    case ReviewApproved = 'review.approved';
    case ReviewRejected = 'review.rejected';
    case ReviewDeleted = 'review.deleted';
    case ReviewSpoilerChanged = 'review.spoiler_changed';
    case MovieUpdated = 'movie.updated';
    case GenreUpdated = 'genre.updated';
    case GenreReset = 'genre.reset';
    case SettingsUpdated = 'settings.updated';
    case ReportResolved = 'report.resolved';
    case ReportDismissed = 'report.dismissed';
    case RatingDeleted = 'rating.deleted';
    /** Every star rating of one member removed at once; the subject is that member. */
    case RatingsPurged = 'rating.purged';

    /** What kind of record the action is done to. */
    public function subjectType(): AdminSubjectType
    {
        return match ($this) {
            self::UserRoleChanged, self::UserSuspended, self::UserReinstated, self::UserDeleted => AdminSubjectType::User,
            self::ReviewApproved, self::ReviewRejected, self::ReviewDeleted, self::ReviewSpoilerChanged => AdminSubjectType::Review,
            self::MovieUpdated => AdminSubjectType::Movie,
            self::GenreUpdated, self::GenreReset => AdminSubjectType::Genre,
            self::SettingsUpdated => AdminSubjectType::Setting,
            self::ReportResolved, self::ReportDismissed => AdminSubjectType::Report,
            self::RatingDeleted => AdminSubjectType::Rating,
            self::RatingsPurged => AdminSubjectType::User,
        };
    }
}
