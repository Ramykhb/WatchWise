<?php

namespace App\Report\DTOs\Requests;

/** Why a member reports a piece of content, with an optional note of their own. */
final readonly class ReportContentData
{
    public const MAX_DETAILS = 500;

    public function __construct(
        public string $reason,
        public ?string $details = null,
    ) {}

    /**
     * @param  array{reason: string, details?: string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['reason'], $validated['details'] ?? null);
    }
}
