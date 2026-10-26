<?php

namespace App\Rating\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Rating\FormRequests\RateMovieRequest;
use App\Rating\InterfaceServices\RatingServiceInterface;
use Illuminate\Http\JsonResponse;

/** The signed-in member's star rating of a film (the Movie page's "Your rating"). */
class RatingController
{
    public function __construct(private readonly RatingServiceInterface $ratings) {}

    /** GET /api/ratings/{tmdbId} — `rating` is null when the member hasn't rated the film. */
    public function show(int $tmdbId, CurrentUser $current): JsonResponse
    {
        return response()->json(['rating' => $this->ratings->forMovie($current->user(), $tmdbId)]);
    }

    /** PUT /api/ratings/{tmdbId} — `rating` 1–5. */
    public function update(RateMovieRequest $request, int $tmdbId, CurrentUser $current): JsonResponse
    {
        return response()->json(['rating' => $this->ratings->rate($current->user(), $tmdbId, $request->rating())]);
    }
}
