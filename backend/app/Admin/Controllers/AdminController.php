<?php

namespace App\Admin\Controllers;

use App\Admin\FormRequests\AdminAuditIndexRequest;
use App\Admin\FormRequests\AdminMovieIndexRequest;
use App\Admin\FormRequests\AdminReviewIndexRequest;
use App\Admin\FormRequests\AdminStatsRequest;
use App\Admin\FormRequests\AdminUserIndexRequest;
use App\Admin\FormRequests\UpdateLocalMovieRequest;
use App\Admin\FormRequests\UpdateUserRequest;
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\InterfaceServices\AdminServiceInterface;
use App\Auth\CurrentUser\CurrentUser;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** The administrator dashboard: stats, user management, review moderation, the local movie catalogue and the audit log. */
class AdminController
{
    public function __construct(
        private readonly AdminServiceInterface $admin,
        private readonly AdminAuditServiceInterface $audit,
    ) {}

    /** GET /api/admin/stats */
    public function stats(AdminStatsRequest $request): JsonResponse
    {
        return response()->json($this->admin->stats($request->toDto()));
    }

    /** GET /api/admin/users */
    public function users(AdminUserIndexRequest $request): JsonResponse
    {
        return response()->json($this->admin->users($request->toDto()));
    }

    /** PATCH /api/admin/users/{user} */
    public function updateUser(UpdateUserRequest $request, User $user, CurrentUser $current): JsonResponse
    {
        return response()->json(['user' => $this->admin->updateUser($current->user(), $user, $request->toDto())]);
    }

    /** DELETE /api/admin/users/{user} */
    public function destroyUser(User $user, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.users.manage');
        $this->admin->deleteUser($current->user(), $user);

        return response()->json(['message' => 'User deleted.']);
    }

    /** GET /api/admin/reviews */
    public function reviews(AdminReviewIndexRequest $request): JsonResponse
    {
        return response()->json($this->admin->reviews($request->toDto()));
    }

    /** DELETE /api/admin/reviews/{review} */
    public function destroyReview(Review $review, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.reviews');
        $this->admin->deleteReview($current->user(), $review);

        return response()->json(['message' => 'Review deleted.']);
    }

    /** GET /api/admin/movies */
    public function movies(AdminMovieIndexRequest $request): JsonResponse
    {
        return response()->json($this->admin->movies($request->toDto()));
    }

    /** PATCH /api/admin/movies/{tmdbId} — hide, feature or edit the local title and overview. */
    public function updateMovie(UpdateLocalMovieRequest $request, int $tmdbId, CurrentUser $current): JsonResponse
    {
        return response()->json(['movie' => $this->admin->updateMovie($current->user(), $tmdbId, $request->toDto())]);
    }

    /** GET /api/admin/audit-log — what staff did, newest first (admins only). */
    public function auditLog(AdminAuditIndexRequest $request): JsonResponse
    {
        return response()->json($this->audit->list($request->toDto()));
    }
}
