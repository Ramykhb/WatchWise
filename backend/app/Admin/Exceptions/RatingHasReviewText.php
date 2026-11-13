<?php

namespace App\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Staff tried to delete a rating that carries review text; that is a review and goes through review moderation. Rendered as a 422. */
class RatingHasReviewText extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This is a review with text, not a star rating. Use review moderation to remove it.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
