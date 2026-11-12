<?php

namespace App\Admin\FormRequests;

use App\Report\DTOs\Requests\ReportQueueQuery;
use App\Report\Support\ReportTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.reports');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(ReportQueueQuery::STATUSES)],
            'target_type' => ['sometimes', 'nullable', Rule::enum(ReportTargetType::class)],
            'reason' => ['sometimes', 'nullable', Rule::in(ReportTargetType::allReasons())],
            'reporter_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.ReportQueueQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): ReportQueueQuery
    {
        return ReportQueueQuery::fromValidated($this->validated());
    }
}
