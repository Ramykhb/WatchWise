<?php

namespace App\Admin\Controllers;

use App\Admin\FormRequests\AdminRatingBurstRequest;
use App\Admin\FormRequests\AdminRatingFilmRequest;
use App\Admin\FormRequests\AdminRatingIndexRequest;
use App\Admin\InterfaceServices\AdminRatingServiceInterface;
use App\Auth\CurrentUser\CurrentUser;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** Star ratings (reviews without text) for staff: the list, one film's picture, rating bursts and removal. */
class AdminRatingController
{
    public function __construct(private readonly AdminRatingServiceInterface $ratings) {}

    /** GET /api/admin/ratings */
    public function index(AdminRatingIndexRequest $request): JsonResponse
    {
        return response()->json($this->ratings->ratings($request->toDto()));
    }

    /** GET /api/admin/ratings/films/{tmdbId}?media_type=movie|tv */
    public function film(AdminRatingFilmRequest $request, int $tmdbId): JsonResponse
    {
        return response()->json($this->ratings->film($tmdbId, $request->mediaType()));
    }

    /** GET /api/admin/ratings/bursts — members who left many star ratings in a short, recent window. */
    public function bursts(AdminRatingBurstRequest $request): JsonResponse
    {
        return response()->json($this->ratings->bursts($request->toDto()));
    }

    /** DELETE /api/admin/ratings/{rating} — one star rating; a review with text is refused. */
    public function destroy(Review $rating, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.ratings');
        $this->ratings->delete($current->user(), $rating);

        return response()->json(['message' => 'Rating deleted.']);
    }

    /** DELETE /api/admin/users/{user}/ratings — every star rating of one member. */
    public function purge(User $user, CurrentUser $current): JsonResponse
    {
        Gate::authorize('admin.ratings');
        $removed = $this->ratings->purge($current->user(), $user);

        return response()->json(['message' => $removed === 1 ? '1 rating removed.' : "{$removed} ratings removed.", 'removed' => $removed]);
    }
}
