<?php

namespace App\Admin\DTOs\Requests;

/** A staff decision on a report: resolved or dismissed, with an optional note and whether it covers every open report on the same content. */
final readonly class UpdateReportData
{
    public const DECISIONS = ['resolved', 'dismissed'];

    public const MAX_NOTE = 500;

    /**
     * @param  'resolved'|'dismissed'  $status
     */
    public function __construct(
        public string $status,
        public ?string $note = null,
        public bool $applyToTarget = true,
    ) {}

    /**
     * @param  array{status: string, note?: string|null, apply_to_target?: bool|int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['status'],
            $validated['note'] ?? null,
            (bool) ($validated['apply_to_target'] ?? true),
        );
    }
}
