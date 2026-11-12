<?php

namespace App\Admin\Services;

use App\Admin\DTOs\Requests\BulkModerateReviewsData;
use App\Admin\DTOs\Requests\UpdateReviewData;
use App\Admin\DTOs\Responses\AdminReviewDetailResponse;
use App\Admin\DTOs\Responses\AdminReviewResponse;
use App\Admin\DTOs\Responses\BulkModerateReviewsResponse;
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\InterfaceServices\AdminReviewToolsServiceInterface;
use App\Admin\InterfaceServices\AdminServiceInterface;
use App\Admin\Support\AdminAction;
use App\Admin\Support\AuditLabel;
use App\Admin\Support\ReviewDecision;
use App\Models\Review;
use App\Models\User;
use App\Review\InterfaceServices\ReviewServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminReviewToolsService implements AdminReviewToolsServiceInterface
{
    public function __construct(
        private readonly AdminServiceInterface $admin,
        private readonly ReviewServiceInterface $reviews,
        private readonly AdminAuditServiceInterface $audit,
    ) {}

    public function detail(Review $review): AdminReviewDetailResponse
    {
        $review->loadMissing(['user', 'reports.reporter:id,name,username', 'reports.resolver:id,name,username']);

        return AdminReviewDetailResponse::fromModel(
            $this->admin->reviewResponse($review),
            $review,
            $this->reviews->moderationContext($review),
        );
    }

    public function update(User $moderator, Review $review, UpdateReviewData $data): AdminReviewResponse
    {
        [$updated, $decision] = DB::transaction(function () use ($moderator, $review, $data): array {
            $decision = $data->decision === null ? null : $this->admin->decideReview($moderator, $review, $data->decision);
            $current = $decision?->review ?? $this->reviews->lockForUpdate($review)->loadMissing('user');

            if ($data->hasSpoilers !== null) {
                $this->changeSpoilers($moderator, $current, $data->hasSpoilers);
            }

            return [$current, $decision];
        });
        $this->admin->settleReviewDecisions($decision === null ? [] : [$decision]);

        return $this->admin->reviewResponse($updated);
    }

    public function bulk(User $moderator, BulkModerateReviewsData $data): BulkModerateReviewsResponse
    {
        $found = $this->reviews->findMany($data->ids);
        $decisions = [];
        $results = [];

        foreach ($data->ids as $id) {
            $review = $found->get($id);

            if ($review === null) {
                $results[] = ['id' => $id, 'status' => 'skipped', 'reason' => 'not_found'];

                continue;
            }

            try {
                $decision = DB::transaction(fn (): ReviewDecision => $this->admin->decideReview($moderator, $review, $data->decision));
            } catch (ModelNotFoundException) {
                $results[] = ['id' => $id, 'status' => 'skipped', 'reason' => 'not_found'];

                continue;
            } catch (Throwable $failure) {
                report($failure);
                $results[] = ['id' => $id, 'status' => 'skipped', 'reason' => 'failed'];

                continue;
            }

            $decisions[] = $decision;
            $results[] = $decision->changed
                ? ['id' => $id, 'status' => $data->decision->decision, 'reason' => null]
                : ['id' => $id, 'status' => 'skipped', 'reason' => 'no_change'];
        }

        $this->admin->settleReviewDecisions($decisions);

        return new BulkModerateReviewsResponse($results, count(array_filter($decisions, fn (ReviewDecision $decision): bool => $decision->changed)));
    }

    /** Records the change only when the flag really moved. Call it inside the transaction that holds the review's row lock. */
    private function changeSpoilers(User $moderator, Review $review, bool $hasSpoilers): void
    {
        $was = (bool) $review->has_spoilers;
        $changed = $this->reviews->changeSpoilers($review, $hasSpoilers);

        if ((bool) $changed->has_spoilers !== $was) {
            $this->audit->record(
                $moderator,
                AdminAction::ReviewSpoilerChanged,
                $changed->id,
                AuditLabel::review($changed),
                ['has_spoilers' => ['from' => $was, 'to' => (bool) $changed->has_spoilers]],
            );
        }
    }
}
