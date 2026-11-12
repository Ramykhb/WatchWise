<?php

namespace App\Admin\Services;

use App\Admin\DTOs\Requests\UpdateReportData;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\DTOs\Responses\AdminReportDecisionResponse;
use App\Admin\DTOs\Responses\AdminReportResponse;
use App\Admin\InterfaceServices\AdminAuditServiceInterface;
use App\Admin\InterfaceServices\AdminReportServiceInterface;
use App\Admin\Support\AdminAction;
use App\Models\Report;
use App\Models\User;
use App\Report\DTOs\Requests\ReportQueueQuery;
use App\Report\InterfaceServices\ReportServiceInterface;
use App\Report\Support\ReportStatus;
use Illuminate\Support\Facades\DB;

class AdminReportService implements AdminReportServiceInterface
{
    /** The most closed report ids one audit entry lists; `reports_closed` always has the full count. */
    private const MAX_LOGGED_IDS = 100;

    public function __construct(
        private readonly ReportServiceInterface $reports,
        private readonly AdminAuditServiceInterface $audit,
    ) {}

    public function reports(ReportQueueQuery $query): AdminPageResponse
    {
        $paginator = $this->reports->queue($query);
        $targets = $this->reports->targets($paginator->items());

        return new AdminPageResponse(
            array_map(fn (Report $report): AdminReportResponse => AdminReportResponse::fromModel($report, $targets[$report->id]), $paginator->items()),
            $paginator->currentPage(),
            $paginator->lastPage(),
            $paginator->perPage(),
            $paginator->total(),
            $this->reports->summary(),
        );
    }

    public function report(Report $report): AdminReportResponse
    {
        $report->loadMissing(['reporter:id,name,username', 'resolver:id,name,username']);

        return AdminReportResponse::fromModel($report, $this->reports->targets([$report])[$report->id]);
    }

    public function decide(User $staff, Report $report, UpdateReportData $data): AdminReportDecisionResponse
    {
        $closedIds = DB::transaction(function () use ($staff, $report, $data): array {
            $closedIds = $this->reports->close($report, $staff, $data->status, $data->note, $data->applyToTarget);

            $this->audit->record(
                $staff,
                $data->status === ReportStatus::Dismissed->value ? AdminAction::ReportDismissed : AdminAction::ReportResolved,
                $report->id,
                'Report on '.$report->target_type->value.' #'.$report->target_id,
                [
                    'status' => ['from' => ReportStatus::Open->value, 'to' => $data->status],
                    'reports_closed' => count($closedIds),
                    'closed_report_ids' => array_slice($closedIds, 0, self::MAX_LOGGED_IDS),
                ],
            );

            return $closedIds;
        });

        return new AdminReportDecisionResponse($this->report($report->refresh()), count($closedIds));
    }
}
