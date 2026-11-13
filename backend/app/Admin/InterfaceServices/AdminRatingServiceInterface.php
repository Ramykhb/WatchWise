<?php

namespace App\Admin\InterfaceServices;

use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\DTOs\Responses\AdminRatingFilmResponse;
use App\Admin\Exceptions\CannotModerateSelf;
use App\Admin\Exceptions\CannotModerateStaff;
use App\Admin\Exceptions\RatingHasReviewText;
use App\Models\Review;
use App\Models\User;
use App\Rating\DTOs\Requests\RatingBurstQuery;
use App\Rating\DTOs\Requests\RatingQuery;

/**
 * Staff tools for star ratings: rows of `reviews` without text. Reviews with text are never listed, counted or
 * removed here; they are moderated through the review tools. Removing ratings tells the member nothing.
 */
interface AdminRatingServiceInterface
{
    /**
     * Star-only ratings, newest first, with the author and the film (from the local catalogue; null when unknown).
     * The page's `summary` (`total`, `average`, `distribution`) covers everything the filters match, not just the page.
     */
    public function ratings(RatingQuery $query): AdminPageResponse;

    /** The star ratings of one title; a title nobody rated answers zeros. */
    public function film(int $tmdbId, string $mediaType): AdminRatingFilmResponse;

    /**
     * Members who created at least `$query->min` star ratings in the trailing `$query->hours` hours (the window ends
     * now), most first. A rating counts when it was first created; changing its stars later does not move it.
     */
    public function bursts(RatingBurstQuery $query): AdminPageResponse;

    /**
     * Removes one star rating and the reports filed against it (they have no foreign key). Recorded in the audit
     * log as `rating.deleted` (the stars go from their value to null, plus `reports_deleted`) in the same
     * transaction; the member is told nothing. Any staff member may delete any one rating.
     *
     * @throws RatingHasReviewText when the row has review text
     * @throws RatingIsModerated when a moderator removed the review's text (`removed_at` is set)
     */
    public function delete(User $staff, Review $rating): void;

    /**
     * Removes every star rating of the member that no moderator touched and returns how many; their reviews with
     * text and the rows of moderated reviews stay, with their reports. A purge that removes nothing records
     * nothing. Recorded as `rating.purged` (`removed: n`, `reports_deleted`) in the same transaction; the member is
     * told nothing. Admins may do this for anyone but themselves, moderators only for plain members.
     *
     * @throws CannotModerateSelf when `$staff` purges their own ratings
     * @throws CannotModerateStaff when a moderator targets an admin or another moderator
     */
    public function purge(User $staff, User $member): int;
}
