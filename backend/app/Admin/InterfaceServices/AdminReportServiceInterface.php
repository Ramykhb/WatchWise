<?php

namespace App\Admin\InterfaceServices;

use App\Admin\DTOs\Requests\UpdateReportData;
use App\Admin\DTOs\Responses\AdminPageResponse;
use App\Admin\DTOs\Responses\AdminReportDecisionResponse;
use App\Admin\DTOs\Responses\AdminReportResponse;
use App\Models\Report;
use App\Models\User;
use App\Report\DTOs\Requests\ReportQueueQuery;
use App\Report\Exceptions\ReportAlreadyClosed;

/** The staff queue of member reports. Triage only: nothing here changes the reported content. */
interface AdminReportServiceInterface
{
    /** One page of reports with what each is about; the page's summary counts reports by status and, of the open ones, by kind of content. */
    public function reports(ReportQueueQuery $query): AdminPageResponse;

    public function report(Report $report): AdminReportResponse;

    /**
     * Closes the report as resolved or dismissed and, unless `applyToTarget` is off, every other open report on
     * the same content with the same decision. Recorded in the audit log with the status before and after and how
     * many reports it closed. Reporters are not told.
     *
     * @throws ReportAlreadyClosed
     */
    public function decide(User $staff, Report $report, UpdateReportData $data): AdminReportDecisionResponse;
}
