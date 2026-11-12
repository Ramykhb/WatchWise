<?php

namespace App\Report\InterfaceServices;

use App\Models\Report;
use App\Models\User;
use App\Report\DTOs\Requests\ReportContentData;
use App\Report\DTOs\Requests\ReportQueueQuery;
use App\Report\DTOs\Responses\ReportContentResponse;
use App\Report\DTOs\Responses\ReportedTargetResponse;
use App\Report\Exceptions\ReportAlreadyClosed;
use App\Report\Support\ReportTargetType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reports on content: reviews, room messages, movie lists, discussion rooms and member profiles. A member files
 * one report per target; staff triage them in a queue and close each as resolved or dismissed. The target's own
 * module decides who may report it and calls this with the target's id; nothing here touches the content.
 */
interface ReportServiceInterface
{
    /**
     * Files the member's report, or hands back the one they already filed (`created` false). A new report keeps a
     * snapshot of the target's label and excerpt, because the content may be gone before staff open it.
     * Reporting the same target again adds no row: while the earlier report is open it stays as it was (a review's
     * reason is replaced, as the review report has always worked), and once staff closed it the report is reopened
     * as new, with the reason, details and snapshot replaced and the closing cleared. Authorization is the caller's job.
     */
    public function report(User $reporter, ReportTargetType $type, int $targetId, ReportContentData $data): ReportContentResponse;

    /**
     * Closes every open report on the target with one decision; for staff actions on the content itself.
     * `$status` is `resolved` or `dismissed`, `$by` the staff member (null for a system action). Returns how many it closed.
     *
     * @param  'resolved'|'dismissed'  $status
     */
    public function resolveForTarget(ReportTargetType $type, int $targetId, ?User $by, string $status): int;

    /**
     * Closes every open report on any of the targets with one decision, like resolveForTarget() for many targets of
     * one kind at once (a deleted room and the messages in it). Returns how many it closed.
     *
     * @param  list<int>  $targetIds
     * @param  'resolved'|'dismissed'  $status
     */
    public function resolveForTargets(ReportTargetType $type, array $targetIds, ?User $by, string $status): int;

    /**
     * How many open reports each of the targets has, in one query however many ids there are; a target without one
     * is present with 0.
     *
     * @param  list<int>  $targetIds
     * @return array<int, int> target id => open reports
     */
    public function openCountsFor(ReportTargetType $type, array $targetIds): array;

    /**
     * The open reports on one target, oldest first, with their `reporter` loaded.
     *
     * @return Collection<int, Report>
     */
    public function openFor(ReportTargetType $type, int $targetId): Collection;

    /**
     * Closes one report as `resolved` or `dismissed` by `$by`, with an optional note, and, with `$applyToTarget`,
     * every other open report on the same target with the same decision and note. Returns the ids it closed, in
     * ascending order. Call inside a transaction: the open reports of the target are row-locked in id order and then
     * the report itself, so two staff members cannot both close it and cannot deadlock on the same target.
     *
     * @param  'resolved'|'dismissed'  $status
     * @return list<int>
     *
     * @throws ReportAlreadyClosed
     */
    public function close(Report $report, User $by, string $status, ?string $note, bool $applyToTarget): array;

    /**
     * One page of the staff queue, newest first, with each report's `reporter` and `resolver` loaded.
     *
     * @return LengthAwarePaginator<int, Report>
     */
    public function queue(ReportQueueQuery $query): LengthAwarePaginator;

    /**
     * Whole-table counts: `by_status` for every status, and `by_target_type` for the reports still open on each kind of content.
     *
     * @return array{by_status: array<string, int>, by_target_type: array<string, int>}
     */
    public function summary(): array;

    /**
     * What each report is about, keyed by report id, in a constant number of queries however many reports there are.
     * A target that is gone is described from the snapshot kept with the report.
     *
     * @param  iterable<Report>  $reports
     * @return array<int, ReportedTargetResponse>
     */
    public function targets(iterable $reports): array;
}
