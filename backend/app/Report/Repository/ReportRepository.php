<?php

namespace App\Report\Repository;

use App\Models\DiscussionRoom;
use App\Models\Message;
use App\Models\MovieList;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Report\DTOs\Requests\ReportQueueQuery;
use App\Report\InterfaceRepository\ReportRepositoryInterface;
use App\Report\Support\ReportStatus;
use App\Report\Support\ReportTargetType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportRepository implements ReportRepositoryInterface
{
    public function createOrFirst(int $reporterId, ReportTargetType $type, int $targetId, string $reason, ?string $details, array $snapshot): Report
    {
        return Report::query()->createOrFirst(
            ['reporter_id' => $reporterId, 'target_type' => $type, 'target_id' => $targetId],
            [
                'reason' => $reason,
                'details' => $details,
                'status' => ReportStatus::Open,
                'target_label' => $snapshot['label'],
                'target_excerpt' => $snapshot['excerpt'],
            ],
        );
    }

    public function reopen(Report $report, string $reason, ?string $details, array $snapshot): Report
    {
        $report->forceFill([
            'status' => ReportStatus::Open,
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_note' => null,
            'reason' => $reason,
            'details' => $details,
            'target_label' => $snapshot['label'],
            'target_excerpt' => $snapshot['excerpt'],
            'created_at' => now(),
        ])->save();

        return $report;
    }

    public function updateReason(Report $report, string $reason): Report
    {
        $report->update(['reason' => $reason]);

        return $report;
    }

    public function paginate(ReportQueueQuery $query): LengthAwarePaginator
    {
        return Report::query()
            ->with(['reporter:id,name,username', 'resolver:id,name,username'])
            ->when($query->status !== null, fn (Builder $builder) => $builder->where('status', $query->status))
            ->when($query->targetType !== null, fn (Builder $builder) => $builder->where('target_type', $query->targetType))
            ->when($query->reason !== null, fn (Builder $builder) => $builder->where('reason', $query->reason))
            ->when($query->reporterId !== null, fn (Builder $builder) => $builder->where('reporter_id', $query->reporterId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function countsByStatus(): array
    {
        $counts = Report::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(ReportStatus::cases())
            ->mapWithKeys(fn (ReportStatus $status): array => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    public function openCountsByTargetType(): array
    {
        $counts = Report::query()->open()->selectRaw('target_type, count(*) as total')->groupBy('target_type')->pluck('total', 'target_type');

        return collect(ReportTargetType::cases())
            ->mapWithKeys(fn (ReportTargetType $type): array => [$type->value => (int) ($counts[$type->value] ?? 0)])
            ->all();
    }

    public function targetRows(array $idsByType): array
    {
        $rows = [];

        foreach ($idsByType as $value => $ids) {
            $rows[$value] = $this->targetQuery(ReportTargetType::from($value))->whereKey($ids)->get()->keyBy('id')->all();
        }

        return $rows;
    }

    public function lockForUpdate(Report $report): Report
    {
        return Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
    }

    public function lockOpenForTarget(ReportTargetType $type, int $targetId): array
    {
        return Report::query()
            ->open()
            ->where('target_type', $type)
            ->where('target_id', $targetId)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function closeMany(array $ids, ReportStatus $status, ?int $resolvedBy, ?string $note): void
    {
        Report::query()
            ->open()
            ->whereKey($ids)
            ->update([
                'status' => $status->value,
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
                'resolution_note' => $note,
            ]);
    }

    /**
     * Each kind of content with the relations its description needs.
     *
     * @return Builder<*>
     */
    private function targetQuery(ReportTargetType $type): Builder
    {
        return match ($type) {
            ReportTargetType::Review => Review::query()->with('user:id,name,username'),
            ReportTargetType::Message => Message::query()->with(['user:id,name,username', 'room:id,name']),
            ReportTargetType::MovieList => MovieList::query()->with('user:id,name,username'),
            ReportTargetType::Room => DiscussionRoom::query()->with('creator:id,name,username'),
            ReportTargetType::Profile => User::query()->select(['id', 'name', 'username']),
        };
    }
}
