<?php

namespace App\Admin\InterfaceServices;

use App\Admin\DTOs\Requests\BulkModerateReviewsData;
use App\Admin\DTOs\Requests\UpdateReviewData;
use App\Admin\DTOs\Responses\AdminReviewDetailResponse;
use App\Admin\DTOs\Responses\AdminReviewResponse;
use App\Admin\DTOs\Responses\BulkModerateReviewsResponse;
use App\Models\Review;
use App\Models\User;
use App\Review\Exceptions\ReviewHasNoText;

/** The per-review tools of the moderation queue: a full view, spoiler flags and decisions on several reviews at once. */
interface AdminReviewToolsServiceInterface
{
    /**
     * The review with its whole text, labels, every report filed against it (open and closed) and a record of its
     * author, in a constant number of queries however many reports it has.
     */
    public function detail(Review $review): AdminReviewDetailResponse;

    /**
     * Applies a decision, a spoiler flag or both in one transaction. The decision works as it does for a bulk
     * request (see `AdminServiceInterface::decideReview()`). A spoiler flag that really changes is recorded in the
     * audit log as `review.spoiler_changed` with the old and new value; setting the value the review already has
     * records nothing. If any part fails, none of it is kept and nobody is told.
     *
     * @throws ReviewHasNoText when a spoiler flag is set on a review without text, or one that the decision just removed
     */
    public function update(User $moderator, Review $review, UpdateReviewData $data): AdminReviewResponse;

    /**
     * Applies one decision to each review in its own transaction, with the same locking, report closing, audit
     * entries and member notices as `update()`. A review that is gone or whose decision fails is skipped and never
     * undoes the others. The cached statistics are retired once, at the end.
     */
    public function bulk(User $moderator, BulkModerateReviewsData $data): BulkModerateReviewsResponse;
}
