<?php

namespace App\Report\DTOs\Requests;

use App\Report\Support\ReportStatus;
use App\Report\Support\ReportTargetType;

/** One page of the staff report queue, narrowed to a status (open unless `all`), a kind of content, a reason or a reporter. */
final readonly class ReportQueueQuery
{
    public const MAX_PER_PAGE = 50;

    /** The statuses the queue can show; `all` lifts the status filter. */
    public const STATUSES = ['open', 'resolved', 'dismissed', 'all'];

    public function __construct(
        public ?ReportStatus $status = ReportStatus::Open,
        public ?ReportTargetType $targetType = null,
        public ?string $reason = null,
        public ?int $reporterId = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}

    /**
     * @param  array{status?: string|null, target_type?: string|null, reason?: string|null, reporter_id?: int|string|null, page?: int|string, per_page?: int|string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $status = $validated['status'] ?? 'open';

        return new self(
            $status === 'all' ? null : ReportStatus::from($status),
            isset($validated['target_type']) ? ReportTargetType::from($validated['target_type']) : null,
            $validated['reason'] ?? null,
            isset($validated['reporter_id']) ? (int) $validated['reporter_id'] : null,
            max(1, (int) ($validated['page'] ?? 1)),
            min(self::MAX_PER_PAGE, max(1, (int) ($validated['per_page'] ?? 20))),
        );
    }
}
