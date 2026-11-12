<?php

namespace App\Admin\Controllers;

use App\Admin\FormRequests\BulkModerateReviewsRequest;
use App\Admin\FormRequests\UpdateReviewRequest;
use App\Admin\InterfaceServices\AdminReviewToolsServiceInterface;
use App\Auth\CurrentUser\CurrentUser;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** What staff do to one review or several: look at it in full, decide on it, flag its spoilers. */
class AdminReviewToolsController
{
    public function __construct(private readonly AdminReviewToolsServiceInterface $tools) {}

    /** GET /api/admin/reviews/{review} */
    public function show(Review $review): JsonResponse
    {
        Gate::authorize('admin.reviews');

        return response()->json(['review' => $this->tools->detail($review)]);
    }

    /** PATCH /api/admin/reviews/{review} — approve or reject it, set its spoiler flag, or both. */
    public function update(UpdateReviewRequest $request, Review $review, CurrentUser $current): JsonResponse
    {
        return response()->json(['review' => $this->tools->update($current->user(), $review, $request->toDto())]);
    }

    /** POST /api/admin/reviews/bulk — one decision for up to 50 reviews. */
    public function bulk(BulkModerateReviewsRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json($this->tools->bulk($current->user(), $request->toDto()));
    }
}
