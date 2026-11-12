<?php

namespace App\Report\DTOs\Responses;

use App\Models\Report;
use JsonSerializable;

/** The outcome of reporting something; `created` is false when the member had already reported it. */
final readonly class ReportContentResponse implements JsonSerializable
{
    public function __construct(
        public bool $created,
        public int $id,
        public string $targetType,
        public int $targetId,
        public string $reason,
        public ?string $details,
        public string $status,
        public string $message,
        public ?string $createdAt,
    ) {}

    public static function fromModel(Report $report, bool $created): self
    {
        return new self(
            created: $created,
            id: $report->id,
            targetType: $report->target_type->value,
            targetId: $report->target_id,
            reason: $report->reason,
            details: $report->details,
            status: $report->status->value,
            message: $report->target_type->label().' reported.',
            createdAt: $report->created_at?->toJSON(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'message' => $this->message,
            'report' => [
                'id' => $this->id,
                'target_type' => $this->targetType,
                'target_id' => $this->targetId,
                'reason' => $this->reason,
                'details' => $this->details,
                'status' => $this->status,
                'created_at' => $this->createdAt,
            ],
        ];
    }
}
