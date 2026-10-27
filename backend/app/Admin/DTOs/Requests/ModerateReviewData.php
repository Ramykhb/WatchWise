<?php

namespace App\Admin\DTOs\Requests;

final readonly class ModerateReviewData
{
    public const DECISIONS = ['approved', 'rejected'];

    /**
     * @param  'approved'|'rejected'  $decision
     */
    public function __construct(public string $decision) {}

    /**
     * @param  array{status: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self($validated['status']);
    }

    public function isApproval(): bool
    {
        return $this->decision === 'approved';
    }
}
