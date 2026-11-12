<?php

namespace App\Review\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Staff tried to flag spoilers on a review that has no text: a plain star rating or one whose text was removed. Rendered as a 409. */
class ReviewHasNoText extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This review has no text to mark as containing spoilers.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
