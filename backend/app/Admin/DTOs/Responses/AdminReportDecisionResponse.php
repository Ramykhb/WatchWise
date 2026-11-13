<?php

namespace App\Admin\DTOs\Responses;

use JsonSerializable;

/** The outcome of a staff decision on a report, with how many reports it closed (more than one with `apply_to_target`). */
final readonly class AdminReportDecisionResponse implements JsonSerializable
{
    public function __construct(
        public AdminReportResponse $report,
        public int $reportsClosed,
    ) {}

    /**
     * @return array{report: AdminReportResponse, reports_closed: int}
     */
    public function jsonSerialize(): array
    {
        return ['report' => $this->report, 'reports_closed' => $this->reportsClosed];
    }
}
