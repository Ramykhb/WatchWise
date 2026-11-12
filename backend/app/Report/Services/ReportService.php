<?php

namespace App\Report\Services;

use App\Models\Report;
use App\Models\User;
use App\Report\DTOs\Requests\ReportContentData;
use App\Report\DTOs\Requests\ReportQueueQuery;
use App\Report\DTOs\Responses\ReportContentResponse;
use App\Report\DTOs\Responses\ReportedTargetResponse;
use App\Report\Exceptions\ReportAlreadyClosed;
use App\Report\InterfaceRepository\ReportRepositoryInterface;
use App\Report\InterfaceServices\ReportServiceInterface;
use App\Report\Support\ReportStatus;
use App\Report\Support\ReportTargetType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReportService implements ReportServiceInterface
{
    /** Longest label and excerpt kept with a report. */
    private const LABEL_LENGTH = 120;

    private const EXCERPT_LENGTH = 200;

    public function __construct(private readonly ReportRepositoryInterface $reports) {}

    public function report(User $reporter, ReportTargetType $type, int $targetId, ReportContentData $data): ReportContentResponse
    {
        $snapshot = $this->snapshot($type, $targetId);
        $report = $this->reports->createOrFirst($reporter->id, $type, $targetId, $data->reason, $data->details, $snapshot);
        $created = $report->wasRecentlyCreated;

        if (! $created) {
            $report = DB::transaction(fn (): Report => $this->repeat($report, $type, $data, $snapshot));
        }

        return ReportContentResponse::fromModel($report, $created);
    }

    /**
     * The member reported the same content again. A closed report comes back to the queue as new (staff decided on
     * what was reported before); an open one stays as it was, except that a review's reason is replaced.
     *
     * @param  array{label: ?string, excerpt: ?string}  $snapshot
     */
    private function repeat(Report $report, ReportTargetType $type, ReportContentData $data, array $snapshot): Report
    {
        $current = $this->reports->lockForUpdate($report);

        if ($current->status !== ReportStatus::Open) {
            return $this->reports->reopen($current, $data->reason, $data->details, $snapshot);
        }

        if ($type->replacesReasonOnRepeat() && $current->reason !== $data->reason) {
            return $this->reports->updateReason($current, $data->reason);
        }

        return $current;
    }

    /**
     * What the content looks like now, kept with the report because it may be gone by the time staff open it.
     *
     * @return array{label: ?string, excerpt: ?string}
     */
    private function snapshot(ReportTargetType $type, int $targetId): array
    {
        $row = $this->reports->targetRows([$type->value => [$targetId]])[$type->value][$targetId] ?? null;

        if ($row === null) {
            return ['label' => null, 'excerpt' => null];
        }

        $target = ReportedTargetResponse::describe($type, $targetId, $row);

        return [
            'label' => mb_substr($target->label, 0, self::LABEL_LENGTH),
            'excerpt' => $target->excerpt === null ? null : mb_substr($target->excerpt, 0, self::EXCERPT_LENGTH),
        ];
    }

    public function resolveForTarget(ReportTargetType $type, int $targetId, ?User $by, string $status): int
    {
        $decision = ReportStatus::closing($status);
        $ids = $this->reports->lockOpenForTarget($type, $targetId);
        $this->reports->closeMany($ids, $decision, $by?->id, null);

        return count($ids);
    }

    public function openCountsFor(ReportTargetType $type, array $targetIds): array
    {
        return $targetIds === [] ? [] : $this->reports->openCountsFor($type, $targetIds);
    }

    public function openFor(ReportTargetType $type, int $targetId): Collection
    {
        return $this->reports->openFor($type, $targetId);
    }

    public function close(Report $report, User $by, string $status, ?string $note, bool $applyToTarget): array
    {
        $decision = ReportStatus::closing($status);
        $targetIds = $applyToTarget ? $this->reports->lockOpenForTarget($report->target_type, $report->target_id) : [];
        $current = $this->reports->lockForUpdate($report);

        if ($current->status !== ReportStatus::Open) {
            throw new ReportAlreadyClosed;
        }

        $ids = $applyToTarget ? $targetIds : [$current->id];
        $this->reports->closeMany($ids, $decision, $by->id, $note);

        return $ids;
    }

    public function queue(ReportQueueQuery $query): LengthAwarePaginator
    {
        return $this->reports->paginate($query);
    }

    public function summary(): array
    {
        return [
            'by_status' => $this->reports->countsByStatus(),
            'by_target_type' => $this->reports->openCountsByTargetType(),
        ];
    }

    public function targets(iterable $reports): array
    {
        $idsByType = [];

        foreach ($reports as $report) {
            $idsByType[$report->target_type->value][] = $report->target_id;
        }

        $rows = $this->reports->targetRows(array_map(fn (array $ids): array => array_values(array_unique($ids)), $idsByType));
        $targets = [];

        foreach ($reports as $report) {
            $targets[$report->id] = ReportedTargetResponse::describe(
                $report->target_type,
                $report->target_id,
                $rows[$report->target_type->value][$report->target_id] ?? null,
                $report->target_label,
                $report->target_excerpt,
            );
        }

        return $targets;
    }
}
