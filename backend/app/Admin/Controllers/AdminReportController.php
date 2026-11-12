<?php

namespace App\Admin\Controllers;

use App\Admin\FormRequests\AdminReportIndexRequest;
use App\Admin\FormRequests\UpdateReportRequest;
use App\Admin\InterfaceServices\AdminReportServiceInterface;
use App\Auth\CurrentUser\CurrentUser;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** The staff queue of member reports on reviews, messages, lists, rooms and profiles. */
class AdminReportController
{
    public function __construct(private readonly AdminReportServiceInterface $reports) {}

    /** GET /api/admin/reports — open by default, newest first. */
    public function index(AdminReportIndexRequest $request): JsonResponse
    {
        return response()->json($this->reports->reports($request->toDto()));
    }

    /** GET /api/admin/reports/{report} */
    public function show(Report $report): JsonResponse
    {
        Gate::authorize('admin.reports');

        return response()->json(['report' => $this->reports->report($report)]);
    }

    /** PATCH /api/admin/reports/{report} — resolve or dismiss, with an optional note. */
    public function update(UpdateReportRequest $request, Report $report, CurrentUser $current): JsonResponse
    {
        return response()->json($this->reports->decide($current->user(), $report, $request->toDto()));
    }
}
