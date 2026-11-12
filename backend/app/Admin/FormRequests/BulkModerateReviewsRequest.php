<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\BulkModerateReviewsData;
use App\Admin\DTOs\Requests\ModerateReviewData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkModerateReviewsRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:'.BulkModerateReviewsData::MAX_REVIEWS],
            'ids.*' => ['integer', 'min:1', 'distinct'],
            'status' => ['required', Rule::in(ModerateReviewData::DECISIONS)],
        ];
    }

    public function toDto(): BulkModerateReviewsData
    {
        return BulkModerateReviewsData::fromValidated($this->validated());
    }
}
