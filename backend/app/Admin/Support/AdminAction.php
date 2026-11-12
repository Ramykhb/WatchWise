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
    case MovieUpdated = 'movie.updated';
    case GenreUpdated = 'genre.updated';
    case GenreReset = 'genre.reset';

    /** What kind of record the action is done to. */
    public function subjectType(): AdminSubjectType
    {
        return match ($this) {
            self::UserRoleChanged, self::UserSuspended, self::UserReinstated, self::UserDeleted => AdminSubjectType::User,
            self::ReviewApproved, self::ReviewRejected, self::ReviewDeleted => AdminSubjectType::Review,
            self::MovieUpdated => AdminSubjectType::Movie,
            self::GenreUpdated, self::GenreReset => AdminSubjectType::Genre,
        };
    }
}
