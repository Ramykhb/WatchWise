<?php

namespace App\Admin\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Staff tried to delete the star rating left behind by a review whose text a moderator removed; review moderation owns that row. Rendered as a 422. */
class RatingIsModerated extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This row belongs to a moderated review, not a plain star rating. Use review moderation to remove it.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
