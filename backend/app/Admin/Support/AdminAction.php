<?php

namespace App\Admin\Support;

/** Every staff action the audit log records. Stored as the string value; never rename one, rows keep it. */
enum AdminAction: string
{
    case UserRoleChanged = 'user.role_changed';
    case UserSuspended = 'user.suspended';
    case UserReinstated = 'user.reinstated';
    case UserDeleted = 'user.deleted';
    case UserNoteAdded = 'user.note_added';
    case UserNoteDeleted = 'user.note_deleted';
    case UserSignedOut = 'user.signed_out';
    case UserAvatarRemoved = 'user.avatar_removed';
    case UserBioRemoved = 'user.bio_removed';
    case UserVerificationResent = 'user.verification_resent';
    case UserSuspensionLifted = 'user.suspension_lifted';
    case ReviewApproved = 'review.approved';
    case ReviewRejected = 'review.rejected';
    case ReviewDeleted = 'review.deleted';
    case MovieUpdated = 'movie.updated';
    case GenreUpdated = 'genre.updated';
    case GenreReset = 'genre.reset';
    case SettingsUpdated = 'settings.updated';
    case ReportResolved = 'report.resolved';
    case ReportDismissed = 'report.dismissed';

    /** What kind of record the action is done to. */
    public function subjectType(): AdminSubjectType
    {
        return match ($this) {
            self::UserRoleChanged, self::UserSuspended, self::UserReinstated, self::UserDeleted => AdminSubjectType::User,
            self::UserNoteAdded, self::UserNoteDeleted, self::UserSignedOut, self::UserAvatarRemoved, self::UserBioRemoved,
            self::UserVerificationResent, self::UserSuspensionLifted => AdminSubjectType::User,
            self::ReviewApproved, self::ReviewRejected, self::ReviewDeleted => AdminSubjectType::Review,
            self::MovieUpdated => AdminSubjectType::Movie,
            self::GenreUpdated, self::GenreReset => AdminSubjectType::Genre,
            self::SettingsUpdated => AdminSubjectType::Setting,
            self::ReportResolved, self::ReportDismissed => AdminSubjectType::Report,
        };
    }
}
