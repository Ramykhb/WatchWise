<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\ReviewModerationQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminReviewIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.reviews');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(ReviewModerationQuery::STATUSES)],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'sentiment' => ['sometimes', 'nullable', Rule::in(ReviewModerationQuery::SENTIMENTS)],
            'reason' => ['sometimes', 'nullable', Rule::in(ReviewModerationQuery::REASONS)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.ReviewModerationQuery::MAX_PER_PAGE],
        ];
    }

    public function toDto(): ReviewModerationQuery
    {
        return ReviewModerationQuery::fromValidated($this->validated());
    }
}
