<?php

namespace App\Admin\DTOs\Responses;

use App\Models\Report;
use App\Models\User;
use App\Report\DTOs\Responses\ReportedTargetResponse;
use JsonSerializable;

/** A report in the staff queue: who filed it, why, what it is about and how it was closed. */
final readonly class AdminReportResponse implements JsonSerializable
{
    /**
     * @param  'open'|'resolved'|'dismissed'  $status
     * @param  array{id: int, name: string, username: ?string}|null  $reporter
     * @param  array{id: int, name: string, username: ?string}|null  $resolvedBy
     */
    public function __construct(
        public int $id,
        public string $status,
        public string $reason,
        public ?string $details,
        public ?array $reporter,
        public ReportedTargetResponse $target,
        public ?array $resolvedBy,
        public ?string $resolutionNote,
        public ?string $createdAt,
        public ?string $resolvedAt,
    ) {}

    /** Needs the report's `reporter` and `resolver` loaded; `$target` says what it is about. */
    public static function fromModel(Report $report, ReportedTargetResponse $target): self
    {
        return new self(
            id: $report->id,
            status: $report->status->value,
            reason: $report->reason,
            details: $report->details,
            reporter: self::member($report->reporter),
            target: $target,
            resolvedBy: self::member($report->resolver),
            resolutionNote: $report->resolution_note,
            createdAt: $report->created_at?->toJSON(),
            resolvedAt: $report->resolved_at?->toJSON(),
        );
    }

    /**
     * @return array{id: int, name: string, username: ?string}|null
     */
    private static function member(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name, 'username' => $user->username];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'reason' => $this->reason,
            'details' => $this->details,
            'reporter' => $this->reporter,
            'target' => $this->target,
            'resolved_by' => $this->resolvedBy,
            'resolution_note' => $this->resolutionNote,
            'created_at' => $this->createdAt,
            'resolved_at' => $this->resolvedAt,
        ];
    }
}
