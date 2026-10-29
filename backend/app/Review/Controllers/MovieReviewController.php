<?php

namespace App\Review\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\Review;
use App\Review\FormRequests\MovieReviewIndexRequest;
use App\Review\FormRequests\MyReviewRequest;
use App\Review\FormRequests\ReportReviewRequest;
use App\Review\FormRequests\SaveReviewRequest;
use App\Review\InterfaceServices\MovieReviewServiceInterface;
use Illuminate\Http\JsonResponse;

/** The Movie page's Reviews section, for movies and TV shows. */
class MovieReviewController
{
    public function __construct(private readonly MovieReviewServiceInterface $reviews) {}

    /** GET /api/reviews?tmdb_id=&type=&sort=helpful|newest|highest|lowest&page= */
    public function index(MovieReviewIndexRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json($this->reviews->forTitle($request->toDto(), $current->user()));
    }

    /** PUT /api/reviews/mine — `tmdb_id`, `type`, `content`, `rating` 1–5, `has_spoilers`. */
    public function save(SaveReviewRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json(['review' => $this->reviews->save($current->user(), $request->toDto())]);
    }

    /** DELETE /api/reviews/mine?tmdb_id=&type= — removes the text; the star rating stays. */
    public function destroy(MyReviewRequest $request, CurrentUser $current): JsonResponse
    {
        $this->reviews->delete($current->user(), $request->tmdbId(), $request->mediaType());

        return response()->json(['message' => 'Review deleted.']);
    }

    /** POST /api/reviews/{review}/report — `reason`: Spam, Links, Spoilers or Offensive language. 201 when new, 200 when changed. */
    public function report(ReportReviewRequest $request, Review $review, CurrentUser $current): JsonResponse
    {
        $result = $this->reviews->report($review, $current->user(), $request->toDto());

        return response()->json($result, $result->created ? 201 : 200);
    }

    /** POST /api/reviews/{review}/helpful */
    public function helpful(Review $review, CurrentUser $current): JsonResponse
    {
        return response()->json(['helpful' => $this->reviews->markHelpful($review, $current->user(), true), 'marked_helpful' => true]);
    }

    /** DELETE /api/reviews/{review}/helpful */
    public function unhelpful(Review $review, CurrentUser $current): JsonResponse
    {
        return response()->json(['helpful' => $this->reviews->markHelpful($review, $current->user(), false), 'marked_helpful' => false]);
    }
}
