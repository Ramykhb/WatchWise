<?php

namespace App\Admin\InterfaceServices;

use App\Admin\DTOs\Requests\AddUserNoteData;
use App\Admin\DTOs\Requests\BulkSuspendData;
use App\Admin\DTOs\Requests\UserNoteQuery;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\DTOs\Responses\AdminUserDetailResponse;
use App\Admin\DTOs\Responses\AdminUserNoteResponse;
use App\Admin\DTOs\Responses\BulkSuspendResponse;
use App\Admin\Exceptions\MemberAlreadyVerified;
use App\Admin\Exceptions\NoteNotFound;
use App\Admin\Exceptions\NothingToRemove;
use App\Admin\Exceptions\NotYourNote;
use App\Models\AdminUserNote;
use App\Models\User;

/**
 * What staff can do for one member beyond the user table: look at everything about them, keep internal notes,
 * sign them out, clean up their profile, resend their verification email and suspend several at once.
 * Admins may act on anyone but themselves (`CannotModerateSelf`); moderators only on plain `user` accounts
 * (`CannotModerateStaff`), never on themselves. Both apply to every method here except `detail()`, which anyone on
 * staff may read, and the bulk and scheduled ones, which apply the rules of a single suspension. Every change is
 * recorded in the audit log in the same transaction, and nothing is recorded when nothing changed.
 */
interface AdminUserToolsServiceInterface
{
    /**
     * The member with their profile, counts, linked providers (names only), open sessions, suspension, note count and
     * the last 10 audit entries about them. The audit entries are for admins; a moderator gets `null` for them.
     */
    public function detail(User $viewer, User $target): AdminUserDetailResponse;

    /** The member's notes, newest first. */
    public function notes(User $viewer, User $target, UserNoteQuery $query): AdminPageResponse;

    /** Recorded as `user.note_added` (never with the note's text). */
    public function addNote(User $author, User $target, AddUserNoteData $data): AdminUserNoteResponse;

    /**
     * Admins delete any note, a moderator only their own. Recorded as `user.note_deleted` (never with the note's text).
     *
     * @throws NoteNotFound when the note is not about this member or is already gone
     * @throws NotYourNote when a moderator deletes another staff member's note
     */
    public function deleteNote(User $actor, User $target, AdminUserNote $note): void;

    /** Ends every session of the member (`user.signed_out`); the member is not told. */
    public function signOut(User $actor, User $target): void;

    /**
     * Removes the member's profile picture (an uploaded file is deleted, an external URL forgotten), records
     * `user.avatar_removed` and tells the member with a `profile_changed` notification. Open reports are left for staff to close.
     *
     * @throws NothingToRemove when the member has none
     */
    public function removeAvatar(User $actor, User $target): void;

    /**
     * Empties the member's bio, records `user.bio_removed` and tells the member with a `profile_changed`
     * notification. Open reports are left for staff to close.
     *
     * @throws NothingToRemove when the member has none
     */
    public function removeBio(User $actor, User $target): void;

    /**
     * Mails the verification link again through the Auth module and records `user.verification_resent`.
     *
     * @throws MemberAlreadyVerified
     */
    public function resendVerification(User $actor, User $target): void;

    /**
     * Suspends each member with the rules of a single suspension, one at a time and each in its own transaction, so
     * one that fails or is refused never undoes the others. A member that is not allowed, is `$actor`, does not
     * exist, is already suspended, or whose suspension failed unexpectedly is reported as skipped.
     */
    public function bulkSuspend(User $actor, BulkSuspendData $data): BulkSuspendResponse;

    /**
     * Reinstates every suspended member whose end has passed, as the system (`user.suspension_lifted`, no actor), and
     * emails them. Each member is handled in its own transaction after re-checking their row under a lock, so running
     * it twice or while staff reinstate someone changes nothing twice. Returns how many it reinstated.
     */
    public function liftExpiredSuspensions(): int;
}
