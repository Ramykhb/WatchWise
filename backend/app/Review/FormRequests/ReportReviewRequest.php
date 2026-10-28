<?php

namespace App\Review\FormRequests;

use App\Review\DTOs\Requests\ReportReviewData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** POST /api/reviews/{review}/report — a member reports another member's published review. */
class ReportReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('report', $this->route('review'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(ReportReviewData::reasons())],
        ];
    }

    public function toDto(): ReportReviewData
    {
        return ReportReviewData::fromValidated($this->validated());
    }
}
