<?php

namespace App\Review\DTOs\Requests;

use App\Models\ReviewReport;

/** Why a member reports someone's review. */
final readonly class ReportReviewData
{
    /**
     * @param  'Spam'|'Links'|'Spoilers'|'Offensive language'  $reason
     */
    public function __construct(public string $reason) {}

    /**
     * @param  array{reason: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['reason']);
    }

    /**
     * @return list<string>
     */
    public static function reasons(): array
    {
        return ReviewReport::REASONS;
    }
}
