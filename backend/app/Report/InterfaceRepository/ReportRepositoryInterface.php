<?php

namespace App\Report\InterfaceRepository;

use App\Models\Report;
use App\Report\DTOs\Requests\ReportQueueQuery;
use App\Report\Support\ReportStatus;
use App\Report\Support\ReportTargetType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReportRepositoryInterface
{
    /**
     * The member's report of the target, created when they have none yet, with `$snapshot` (the target's label and
     * excerpt as they are now) stored on a new row only. Safe against two requests racing: the unique key decides,
     * and the loser gets the winner's row. A new row is `wasRecentlyCreated`.
     *
     * @param  array{label: ?string, excerpt: ?string}  $snapshot
     */
    public function createOrFirst(int $reporterId, ReportTargetType $type, int $targetId, string $reason, ?string $details, array $snapshot): Report;

    public function updateReason(Report $report, string $reason): Report;

    /**
     * Puts a closed report back in the queue as if it were new: open, the closing cleared, the reason, details and
     * snapshot replaced, and the creation time moved to now.
     *
     * @param  array{label: ?string, excerpt: ?string}  $snapshot
     */
    public function reopen(Report $report, string $reason, ?string $details, array $snapshot): Report;

    /**
     * Newest first, then by id, with `reporter` and `resolver` loaded.
     *
     * @return LengthAwarePaginator<int, Report>
     */
    public function paginate(ReportQueueQuery $query): LengthAwarePaginator;

    /**
     * Reports in each status; every status is present.
     *
     * @return array<string, int>
     */
    public function countsByStatus(): array;

    /**
     * Open reports on each kind of content; every kind is present.
     *
     * @return array<string, int>
     */
    public function openCountsByTargetType(): array;

    /**
     * Open reports per target among `$targetIds`; targets without any are absent.
     *
     * @param  list<int>  $targetIds
     * @return array<int, int>
     */
    public function openCountsFor(ReportTargetType $type, array $targetIds): array;

    /**
     * Open reports on the target, newest first, then by id, with `reporter` loaded.
     *
     * @return Collection<int, Report>
     */
    public function openFor(ReportTargetType $type, int $targetId): Collection;

    /**
     * The rows reports point at, found in a constant number of queries (one per kind) with the author each description needs loaded.
     * Ids that no longer exist are left out.
     *
     * @param  array<string, list<int>>  $idsByType  keyed by ReportTargetType value
     * @return array<string, array<int, Model>> the rows, keyed by type value and then id
     */
    public function targetRows(array $idsByType): array;

    /** A fresh copy of the report, row-locked until the surrounding transaction ends (call inside one). */
    public function lockForUpdate(Report $report): Report;

    /**
     * The ids of the open reports on the target, in ascending order, row-locked until the surrounding transaction
     * ends. Locking in one fixed order keeps two staff members closing reports of the same target from deadlocking.
     *
     * @return list<int>
     */
    public function lockOpenForTarget(ReportTargetType $type, int $targetId): array;

    /**
     * Closes the reports that are still open among `$ids`.
     *
     * @param  list<int>  $ids
     */
    public function closeMany(array $ids, ReportStatus $status, ?int $resolvedBy, ?string $note): void;
}
