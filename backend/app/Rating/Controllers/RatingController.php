<?php

namespace App\Rating\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Rating\FormRequests\RateMovieRequest;
use App\Rating\FormRequests\ShowRatingRequest;
use App\Rating\InterfaceServices\RatingServiceInterface;
use Illuminate\Http\JsonResponse;

/** The signed-in member's star rating of a movie or TV show (the Movie page's "Your rating"). */
class RatingController
{
    public function __construct(private readonly RatingServiceInterface $ratings) {}

    /** GET /api/ratings/{tmdbId}?type=movie|tv — `rating` is null when the member hasn't rated the title. */
    public function show(ShowRatingRequest $request, int $tmdbId, CurrentUser $current): JsonResponse
    {
        return response()->json(['rating' => $this->ratings->forMovie($current->user(), $tmdbId, $request->mediaType())]);
    }

    /** PUT /api/ratings/{tmdbId} — `rating` 1–5, `type` movie (default) or tv. */
    public function update(RateMovieRequest $request, int $tmdbId, CurrentUser $current): JsonResponse
    {
        return response()->json(['rating' => $this->ratings->rate($current->user(), $tmdbId, $request->rating(), $request->mediaType())]);
    }
}
