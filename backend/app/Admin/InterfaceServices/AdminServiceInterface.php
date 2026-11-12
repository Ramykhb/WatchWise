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
use App\Models\Review;
use App\Models\User;
use App\Movie\DTOs\Requests\LocalMovieQuery;
use App\Movie\DTOs\Requests\UpdateLocalMovieData;
use App\Movie\Exceptions\MovieNotFound;

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

    /** @throws CannotModerateSelf when `$admin` deletes themselves */
    public function deleteUser(User $admin, User $target): void;

    public function reviews(ReviewModerationQuery $query): AdminPageResponse;

    /**
     * Recorded in the audit log as approved or rejected, with the review's status before and after and how many
     * open reports the decision closed; a decision that changes neither records nothing. Rejecting a review that
     * still has text tells its author it was removed; approving tells nobody.
     */
    public function moderateReview(User $moderator, Review $review, ModerateReviewData $data): AdminReviewResponse;

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
