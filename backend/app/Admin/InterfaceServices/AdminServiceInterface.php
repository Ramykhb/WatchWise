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

    public function users(AdminUserQuery $query): AdminPageResponse;

    /**
     * Deactivating an account also signs it out everywhere.
     *
     * @throws CannotModerateSelf when `$admin` removes their own admin role or deactivates themselves
     */
    public function updateUser(User $admin, User $target, UpdateUserData $data): AdminUserResponse;

    /** @throws CannotModerateSelf when `$admin` deletes themselves */
    public function deleteUser(User $admin, User $target): void;

    public function reviews(ReviewModerationQuery $query): AdminPageResponse;

    public function moderateReview(Review $review, ModerateReviewData $data): AdminReviewResponse;

    public function deleteReview(Review $review): void;

    public function movies(LocalMovieQuery $query): AdminPageResponse;

    /** @throws MovieNotFound */
    public function updateMovie(int $tmdbId, UpdateLocalMovieData $data): AdminMovieResponse;
}
