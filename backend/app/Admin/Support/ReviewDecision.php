<?php

namespace App\Admin\Support;

use App\Models\Review;

/**
 * What one approve or reject decision did to a review. `$changed` is false when the review already stood where the
 * decision puts it and no open report was closed; such a decision is not audited. `$removedText` is true when the
 * decision took away text that its author has to be told about.
 */
final readonly class ReviewDecision
{
    public function __construct(
        public Review $review,
        public bool $changed,
        public bool $removedText,
    ) {}
}
