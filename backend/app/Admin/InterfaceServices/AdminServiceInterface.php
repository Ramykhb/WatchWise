<?php

namespace App\Admin\InterfaceServices;

use App\Admin\DTOs\Requests\AdminStatsQuery;
use App\Admin\DTOs\Requests\AdminUserQuery;
use App\Admin\DTOs\Requests\ModerateReviewData;
use App\Admin\DTOs\Requests\ReviewModerationQuery;
use App\Admin\DTOs\Requests\UpdateUserData;
use App\Admin\DTOs\Responses\AdminMovieResponse;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\DTOs\Responses\AdminReviewResponse;
use App\Admin\DTOs\Responses\AdminStatsResponse;
use App\Admin\DTOs\Responses\AdminUserResponse;
use App\Admin\Exceptions\CannotModerateSelf;
use App\Admin\Exceptions\CannotModerateStaff;
use App\Admin\Support\ReviewDecision;
use App\Models\Review;
use App\Models\User;
use App\Movie\DTOs\Requests\LocalMovieQuery;
use App\Movie\DTOs\Requests\UpdateLocalMovieData;
use App\Movie\Exceptions\MovieNotFound;
use Carbon\CarbonInterface;

interface AdminServiceInterface
{
    public function stats(AdminStatsQuery $query): AdminStatsResponse;

    /** Retires every cached stats window, so the next request counts afresh. Call it once the change has committed. */
    public function forgetStats(): void;

    public function users(AdminUserQuery $query): AdminPageResponse;

    /**
     * Deactivating an account also signs it out everywhere. A role change and an active-state change are each
     * recorded in the audit log (one entry apiece), but only when the value really changed. A real suspension or
     * reinstatement also emails the member's stored address once the change is saved; role changes send nothing.
     *
     * @throws CannotModerateSelf when `$admin` removes their own admin role or deactivates themselves
     */
    public function updateUser(User $admin, User $target, UpdateUserData $data): AdminUserResponse;

    /**
     * Suspends the member with the same rules, audit entry, email and sign-out as `updateUser()` with `is_active`
     * false; `$reason` and `$until` are kept and shown to the member. Returns false when the account was already
     * suspended (nothing changes, nothing is recorded). Once the suspension is saved a failure to end the member's sessions
     * is only reported, so the caller still learns that the member is suspended.
     *
     * @throws CannotModerateSelf when `$admin` suspends themselves
     * @throws CannotModerateStaff when a moderator suspends an admin or a moderator
     */
    public function suspendUser(User $admin, User $target, ?string $reason, ?CarbonInterface $until): bool;

    /** @throws CannotModerateSelf when `$admin` deletes themselves */
    public function deleteUser(User $admin, User $target): void;

    public function reviews(ReviewModerationQuery $query): AdminPageResponse;

    /**
     * Approves or rejects one review on a row-locked re-read, closing its open reports. Recorded in the audit log as
     * approved or rejected, with the review's status before and after and how many open reports the decision
     * closed; a decision that changes neither records nothing. Call it inside a transaction: it sends nothing and
     * clears no cache, so that a failure further on can still undo it. Once the transaction committed, hand the
     * decisions to `settleReviewDecisions()`.
     */
    public function decideReview(User $moderator, Review $review, ModerateReviewData $data): ReviewDecision;

    /**
     * Follows up committed decisions, however many: retires the cached statistics once, and tells the author of
     * each review whose text was removed (approving tells nobody). A failure to notify never undoes a decision.
     *
     * @param  list<ReviewDecision>  $decisions
     */
    public function settleReviewDecisions(array $decisions): void;

    /** The review as a queue row, with its author, reports and film. */
    public function reviewResponse(Review $review): AdminReviewResponse;

    /** Deleting a review that still has text tells its author it was removed. */
    public function deleteReview(User $moderator, Review $review): void;

    public function movies(LocalMovieQuery $query): AdminPageResponse;

    /**
     * Recorded in the audit log when a submitted field really changed, with old and new values (an overview is only marked changed).
     *
     * @throws MovieNotFound
     */
    public function updateMovie(User $admin, int $tmdbId, UpdateLocalMovieData $data): AdminMovieResponse;
}
